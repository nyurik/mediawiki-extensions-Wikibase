<?php

namespace Wikibase;

use Maintenance;
use MediaWiki\MediaWikiServices;
use Onoi\MessageReporter\CallbackMessageReporter;
use Onoi\MessageReporter\MessageReporter;
use Wikibase\Repo\Store\PropertyTermsRebuilder;
use Wikibase\Repo\Store\Sql\SqlEntityIdPager;
use Wikibase\Repo\Store\Sql\SqlEntityIdPagerFactory;
use Wikibase\Repo\WikibaseRepo;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * @license GPL-2.0-or-later
 */
class RebuildPropertyTerms extends Maintenance {

	/**
	 * @var WikibaseRepo
	 */
	private $wikibaseRepo;

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Rebuilds property terms from primary persistence' );

		$this->addOption(
			'from-id',
			"First row (page id) to start updating from",
			false,
			true
		);

		$this->addOption(
			'batch-size',
			"Number of rows to update per batch (Default: 250)",
			false,
			true
		);

		$this->addOption(
			'sleep',
			"Sleep time (in seconds) between every batch (Default: 10)",
			false,
			true
		);
	}

	public function execute() {
		if ( !defined( 'WB_VERSION' ) ) {
			$this->fatalError(
				"You need to have Wikibase enabled in order to use this "
				. "maintenance script!\n\n",
				1
			);
		}

		$this->wikibaseRepo = WikibaseRepo::getDefaultInstance();

		$rebuilder = new PropertyTermsRebuilder(
			$this->wikibaseRepo->getNewPropertyTermStoreWriter(),
			$this->newEntityIdPager(),
			$this->getReporter(),
			$this->getErrorReporter(),
			MediaWikiServices::getInstance()->getDBLoadBalancerFactory(),
			$this->wikibaseRepo->getPropertyLookup( Store::LOOKUP_CACHING_RETRIEVE_ONLY ),
			(int)$this->getOption( 'batch-size', 250 ),
			(int)$this->getOption( 'sleep', 10 )
		);

		$rebuilder->rebuild();

		$this->output( "Done.\n" );
	}

	private function newEntityIdPager(): SqlEntityIdPager {
		$sqlEntityIdPagerFactory = new SqlEntityIdPagerFactory(
			$this->wikibaseRepo->getEntityNamespaceLookup(),
			$this->wikibaseRepo->getEntityIdLookup()
		);

		$pager = $sqlEntityIdPagerFactory->newSqlEntityIdPager( [ 'property' ] );

		$fromId = $this->getOption( 'from-id' );

		if ( $fromId !== null ) {
			$pager->setPosition( (int)$fromId - 1 );
		}

		return $pager;
	}

	private function getReporter(): MessageReporter {
		return new CallbackMessageReporter(
			function ( $message ) {
				$this->output( "$message\n" );
			}
		);
	}

	private function getErrorReporter(): MessageReporter {
		return new CallbackMessageReporter(
			function ( $message ) {
				$this->error( "[ERROR] $message" );
			}
		);
	}

}

$maintClass = RebuildPropertyTerms::class;
require_once RUN_MAINTENANCE_IF_MAIN;
