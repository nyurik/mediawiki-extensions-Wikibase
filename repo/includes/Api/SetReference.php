<?php

namespace Wikibase\Repo\Api;

use ApiBase;
use ApiMain;
use Deserializers\Exceptions\DeserializationException;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\ChangeOp\ChangeOpReference;
use Wikibase\Repo\ChangeOp\StatementChangeOpFactory;

/**
 * API module for creating a reference or setting the value of an existing one.
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class SetReference extends ApiBase {

	/**
	 * @var StatementChangeOpFactory
	 */
	private $statementChangeOpFactory;

	/**
	 * @var ApiErrorReporter
	 */
	private $errorReporter;

	/**
	 * @var DeserializerFactory
	 */
	private $deserializerFactory;

	/**
	 * @var StatementModificationHelper
	 */
	private $modificationHelper;

	/**
	 * @var StatementGuidParser
	 */
	private $guidParser;

	/**
	 * @var ResultBuilder
	 */
	private $resultBuilder;

	/**
	 * @var EntitySavingHelper
	 */
	private $entitySavingHelper;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param DeserializerFactory $deserializerFactory
	 * @param ApiErrorReporter $errorReporter
	 * @param StatementChangeOpFactory $statementChangeOpFactory
	 * @param StatementModificationHelper $modificationHelper
	 * @param StatementGuidParser $guidParser
	 * @param callable $resultBuilderInstantiator
	 * @param callable $entitySavingHelperInstantiator
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		DeserializerFactory $deserializerFactory,
		ApiErrorReporter $errorReporter,
		StatementChangeOpFactory $statementChangeOpFactory,
		StatementModificationHelper $modificationHelper,
		StatementGuidParser $guidParser,
		callable $resultBuilderInstantiator,
		callable $entitySavingHelperInstantiator
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->deserializerFactory = $deserializerFactory;
		$this->errorReporter = $errorReporter;
		$this->statementChangeOpFactory = $statementChangeOpFactory;
		$this->modificationHelper = $modificationHelper;
		$this->guidParser = $guidParser;
		$this->resultBuilder = $resultBuilderInstantiator( $this );
		$this->entitySavingHelper = $entitySavingHelperInstantiator( $this );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$this->validateParameters( $params );

		$entityId = $this->guidParser->parse( $params['statement'] )->getEntityId();
		$entity = $this->entitySavingHelper->loadEntity( $entityId );

		$summary = $this->modificationHelper->createSummary( $params, $this );

		$claim = $this->modificationHelper->getStatementFromEntity( $params['statement'], $entity );

		if ( isset( $params['reference'] ) ) {
			$this->validateReferenceHash( $claim, $params['reference'] );
		}

		if ( isset( $params['snaks-order' ] ) ) {
			$snaksOrder = $this->getArrayFromParam( $params['snaks-order'], 'snaks-order' );
		} else {
			$snaksOrder = [];
		}

		$deserializer = $this->deserializerFactory->newSnakListDeserializer();
		/** @var SnakList $snakList */
		try {
			$snakList = $deserializer->deserialize( $this->getArrayFromParam( $params['snaks'], 'snaks' ) );
		} catch ( DeserializationException $e ) {
			$this->errorReporter->dieError(
				'Failed to get reference from reference Serialization ' . $e->getMessage(),
				'snak-instantiation-failure'
			);
		}
		$snakList->orderByProperty( $snaksOrder );

		$newReference = new Reference( $snakList );

		$changeOp = $this->getChangeOp( $newReference );
		$this->modificationHelper->applyChangeOp( $changeOp, $entity, $summary );

		$status = $this->entitySavingHelper->attemptSaveEntity( $entity, $summary );
		$this->resultBuilder->addRevisionIdFromStatusToResult( $status, 'pageinfo' );
		$this->resultBuilder->markSuccess();
		$this->resultBuilder->addReference( $newReference );
	}

	/**
	 * @param array $params
	 */
	private function validateParameters( array $params ) {
		if ( !( $this->modificationHelper->validateStatementGuid( $params['statement'] ) ) ) {
			$this->errorReporter->dieError( 'Invalid claim guid', 'invalid-guid' );
		}
	}

	/**
	 * @param Statement $statement
	 * @param string $referenceHash
	 */
	private function validateReferenceHash( Statement $statement, $referenceHash ) {
		if ( !$statement->getReferences()->hasReferenceHash( $referenceHash ) ) {
			$this->errorReporter->dieError(
				'Statement does not have a reference with the given hash',
				'no-such-reference'
			);
		}
	}

	/**
	 * @param string $arrayParam
	 * @param string $parameter
	 *
	 * @return array
	 */
	private function getArrayFromParam( $arrayParam, $parameter ) {
		$rawArray = json_decode( $arrayParam, true );

		if ( !is_array( $rawArray ) || !count( $rawArray ) ) {
			$this->errorReporter->dieError(
				'No array or invalid JSON given for parameter: ' . $parameter,
				'invalid-json'
			);
		}

		return $rawArray;
	}

	/**
	 * @param Reference $reference
	 *
	 * @return ChangeOpReference
	 */
	private function getChangeOp( Reference $reference ) {
		$params = $this->extractRequestParams();

		$guid = $params['statement'];
		$hash = $params['reference'] ?? '';
		$index = $params['index'] ?? null;

		return $this->statementChangeOpFactory->newSetReferenceOp( $guid, $reference, $hash, $index );
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @see ApiBase::needsToken
	 *
	 * @return string
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	protected function getAllowedParams() {
		return array_merge(
			[
				'statement' => [
					self::PARAM_TYPE => 'string',
					self::PARAM_REQUIRED => true,
				],
				'snaks' => [
					self::PARAM_TYPE => 'text',
					self::PARAM_REQUIRED => true,
				],
				'snaks-order' => [
					self::PARAM_TYPE => 'string',
				],
				'reference' => [
					self::PARAM_TYPE => 'string',
				],
				'index' => [
					self::PARAM_TYPE => 'integer',
				],
				'summary' => [
					self::PARAM_TYPE => 'string',
				],
				'tags' => [
					self::PARAM_TYPE => 'tags',
					self::PARAM_ISMULTI => true,
				],
				'token' => null,
				'baserevid' => [
					self::PARAM_TYPE => 'integer',
				],
				'bot' => false,
			],
			parent::getAllowedParams()
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=wbsetreference&statement=Q76$D4FDE516-F20C-4154-ADCE-7C5B609DFDFF&snaks='
				. '{"P212":[{"snaktype":"value","property":"P212","datavalue":{"type":"string",'
				. '"value":"foo"}}]}&baserevid=7201010&token=foobar'
				=> 'apihelp-wbsetreference-example-1',
			'action=wbsetreference&statement=Q76$D4FDE516-F20C-4154-ADCE-7C5B609DFDFF'
				. '&reference=1eb8793c002b1d9820c833d234a1b54c8e94187e&snaks='
				. '{"P212":[{"snaktype":"value","property":"P212","datavalue":{"type":"string",'
				. '"value":"bar"}}]}&baserevid=7201010&token=foobar'
				=> 'apihelp-wbsetreference-example-2',
			'action=wbsetreference&statement=Q76$D4FDE516-F20C-4154-ADCE-7C5B609DFDFF&snaks='
				. '{"P212":[{"snaktype":"novalue","property":"P212"}]}'
				. '&index=0&baserevid=7201010&token=foobar'
				=> 'apihelp-wbsetreference-example-3',
		];
	}

}
