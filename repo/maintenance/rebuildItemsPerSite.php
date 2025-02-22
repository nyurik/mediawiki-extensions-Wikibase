<?php

namespace Wikibase\Repo\Maintenance;

use Maintenance;
use MediaWiki\MediaWikiServices;
use Onoi\MessageReporter\ObservableMessageReporter;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\Lib\Reporting\ReportingExceptionHandler;
use Wikibase\Lib\Store\Sql\SiteLinkTable;
use Wikibase\Lib\WikibaseSettings;
use Wikibase\Repo\IO\EntityIdReader;
use Wikibase\Repo\IO\LineReader;
use Wikibase\Repo\Store\Sql\ItemsPerSiteBuilder;
use Wikibase\Repo\Store\Sql\SqlEntityIdPager;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Store;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * Maintenance script for rebuilding the wb_items_per_site table.
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class RebuildItemsPerSite extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription(
			'Rebuild the wb_items_per_site table for all existing items. ' .
			'This doesn\'t prune rows belonging to deleted Items, run pruneItemsPerSite.php first for that.'
		);

		$this->addOption( 'batch-size', "Number of rows to update per batch (100 by default)", false, true );

		$this->addOption(
			'file',
			'File path for loading a list of item numeric ids, one numeric id per line. ',
			false,
			true
		);
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( !WikibaseSettings::isRepoEnabled() ) {
			$this->output( "You need to have Wikibase enabled in order to use this maintenance script!\n\n" );
			exit;
		}

		$batchSize = (int)$this->getOption( 'batch-size', 100 );

		$reporter = new ObservableMessageReporter();
		$reporter->registerReporterCallback(
			[ $this, 'report' ]
		);

		$siteLinkTable = new SiteLinkTable( 'wb_items_per_site', false );
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		// Use an uncached EntityLookup here to avoid memory leaks
		$entityLookup = $wikibaseRepo->getEntityLookup( Store::LOOKUP_CACHING_RETRIEVE_ONLY );
		$store = $wikibaseRepo->getStore();
		$builder = new ItemsPerSiteBuilder(
			$siteLinkTable,
			$entityLookup,
			$store->getEntityPrefetcher(),
			MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
		);

		$builder->setReporter( $reporter );
		$builder->setBatchSize( $batchSize );

		$file = $this->getOption( 'file' );
		if ( $file !== null ) {
			$stream = new EntityIdReader(
				new LineReader( fopen( $file, 'r' ) ),
				new ItemIdParser()
			);
			$stream->setExceptionHandler( new ReportingExceptionHandler( $reporter ) );
		} else {
			$stream = new SqlEntityIdPager(
				$wikibaseRepo->getEntityNamespaceLookup(),
				$wikibaseRepo->getEntityIdLookup(),
				[ 'item' ]
			);
		}

		// Now <s>kill</s> fix the table
		$builder->rebuild( $stream );
	}

	/**
	 * Outputs a message vis the output() method.
	 *
	 * @param string $msg
	 */
	public function report( $msg ) {
		$this->output( "$msg\n" );
	}

}

$maintClass = RebuildItemsPerSite::class;
require_once RUN_MAINTENANCE_IF_MAIN;
