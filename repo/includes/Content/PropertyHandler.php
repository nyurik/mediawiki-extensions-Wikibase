<?php

namespace Wikibase\Repo\Content;

use Content;
use IContextSource;
use MediaWiki\Revision\SlotRenderingProvider;
use Page;
use Title;
use Wikibase\Content\EntityHolder;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\EditEntityAction;
use Wikibase\HistoryEntityAction;
use Wikibase\Lib\Store\EntityContentDataCodec;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Lib\Store\EntityTermStoreWriter;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;
use Wikibase\Lib\Store\PropertyInfoStore;
use Wikibase\PropertyContent;
use Wikibase\PropertyInfoBuilder;
use Wikibase\Repo\Search\Fields\FieldDefinitions;
use Wikibase\Repo\Validators\EntityConstraintProvider;
use Wikibase\Repo\Validators\ValidatorErrorLocalizer;
use Wikibase\SubmitEntityAction;
use Wikibase\ViewEntityAction;

/**
 * Content handler for Wikibase items.
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
class PropertyHandler extends EntityHandler {

	/**
	 * @var PropertyInfoStore
	 */
	private $infoStore;

	/**
	 * @var PropertyInfoBuilder
	 */
	private $propertyInfoBuilder;

	/**
	 * @var EntityIdLookup
	 */
	private $entityIdLookup;

	/**
	 * @var LanguageFallbackLabelDescriptionLookupFactory
	 */
	private $labelLookupFactory;

	/**
	 * @var EntityTermStoreWriter[]
	 */
	private $entityTermStoreWriters;

	/**
	 * @param EntityTermStoreWriter[] $entityTermStoreWriters
	 * @param EntityContentDataCodec $contentCodec
	 * @param EntityConstraintProvider $constraintProvider
	 * @param ValidatorErrorLocalizer $errorLocalizer
	 * @param EntityIdParser $entityIdParser
	 * @param EntityIdLookup $entityIdLookup
	 * @param LanguageFallbackLabelDescriptionLookupFactory $labelLookupFactory
	 * @param PropertyInfoStore $infoStore
	 * @param PropertyInfoBuilder $propertyInfoBuilder
	 * @param FieldDefinitions $propertyFieldDefinitions
	 * @param callable|null $legacyExportFormatDetector
	 */
	public function __construct(
		array $entityTermStoreWriters,
		EntityContentDataCodec $contentCodec,
		EntityConstraintProvider $constraintProvider,
		ValidatorErrorLocalizer $errorLocalizer,
		EntityIdParser $entityIdParser,
		EntityIdLookup $entityIdLookup,
		LanguageFallbackLabelDescriptionLookupFactory $labelLookupFactory,
		PropertyInfoStore $infoStore,
		PropertyInfoBuilder $propertyInfoBuilder,
		FieldDefinitions $propertyFieldDefinitions,
		$legacyExportFormatDetector = null
	) {
		parent::__construct(
			CONTENT_MODEL_WIKIBASE_PROPERTY,
			null,
			$contentCodec,
			$constraintProvider,
			$errorLocalizer,
			$entityIdParser,
			$propertyFieldDefinitions,
			$legacyExportFormatDetector
		);

		$this->entityIdLookup = $entityIdLookup;
		$this->labelLookupFactory = $labelLookupFactory;
		$this->infoStore = $infoStore;
		$this->propertyInfoBuilder = $propertyInfoBuilder;
		$this->entityTermStoreWriters = $entityTermStoreWriters;
	}

	/**
	 * @return (\Closure|class-string)[]
	 */
	public function getActionOverrides() {
		return [
			'history' => function( Page $page, IContextSource $context ) {
				return new HistoryEntityAction(
					$page,
					$context,
					$this->entityIdLookup,
					$this->labelLookupFactory->newLabelDescriptionLookup( $context->getLanguage() )
				);
			},
			'view' => ViewEntityAction::class,
			'edit' => EditEntityAction::class,
			'submit' => SubmitEntityAction::class,
		];
	}

	/**
	 * @see EntityHandler::getSpecialPageForCreation
	 *
	 * @return string
	 */
	public function getSpecialPageForCreation() {
		return 'NewProperty';
	}

	/**
	 * Returns Property::ENTITY_TYPE
	 *
	 * @return string
	 */
	public function getEntityType() {
		return Property::ENTITY_TYPE;
	}

	public function getSecondaryDataUpdates(
		Title $title,
		Content $content,
		$role,
		SlotRenderingProvider $slotOutput
	) {
		$updates = parent::getSecondaryDataUpdates( $title, $content, $role, $slotOutput );

		/** @var PropertyContent $content */
		'@phan-var PropertyContent $content';
		$id = $content->getEntityId();
		$property = $content->getProperty();

		$updates[] = new DataUpdateAdapter(
			[ $this->infoStore, 'setPropertyInfo' ],
			$id,
			$this->propertyInfoBuilder->buildPropertyInfo( $property )
		);

		if ( $content->isRedirect() ) {
			foreach ( $this->entityTermStoreWriters as $termStoreWriter ) {
				$updates[] = new DataUpdateAdapter(
					[ $termStoreWriter, 'deleteTermsOfEntity' ],
					$id
				);
			}
		} else {
			foreach ( $this->entityTermStoreWriters as $termStoreWriter ) {
				$updates[] = new DataUpdateAdapter(
					[ $termStoreWriter, 'saveTermsOfEntity' ],
					$property
				);
			}
		}

		return $updates;
	}

	public function getDeletionUpdates( Title $title, $role ) {
		$updates = parent::getDeletionUpdates( $title, $role );

		$id = $this->getIdForTitle( $title );

		$updates[] = new DataUpdateAdapter(
			[ $this->infoStore, 'removePropertyInfo' ],
			$id
		);

		// Unregister the entity from the terms table.
		foreach ( $this->entityTermStoreWriters as $termStoreWriter ) {
			$updates[] = new DataUpdateAdapter(
				[ $termStoreWriter, 'deleteTermsOfEntity' ],
				$id
			);
		}

		return $updates;
	}

	/**
	 * @see EntityHandler::makeEmptyEntity()
	 *
	 * @return Property
	 */
	public function makeEmptyEntity() {
		return Property::newFromType( '' );
	}

	/**
	 * @see EntityHandler::newEntityContent
	 *
	 * @param EntityHolder|null $entityHolder
	 *
	 * @return PropertyContent
	 */
	protected function newEntityContent( EntityHolder $entityHolder = null ) {
		return new PropertyContent( $entityHolder );
	}

	/**
	 * @see EntityContent::makeEntityId
	 *
	 * @param string $id
	 *
	 * @return EntityId
	 */
	public function makeEntityId( $id ) {
		return new PropertyId( $id );
	}

}
