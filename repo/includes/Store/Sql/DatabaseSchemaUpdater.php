<?php

namespace Wikibase\Repo\Store\Sql;

use DatabaseUpdater;
use HashBagOStuff;
use MediaWiki\MediaWikiServices;
use MWException;
use Onoi\MessageReporter\ObservableMessageReporter;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\LegacyAdapterPropertyLookup;
use Wikibase\Lib\Store\CachingEntityRevisionLookup;
use Wikibase\Lib\Store\EntityRevisionCache;
use Wikibase\Lib\Store\RevisionBasedEntityLookup;
use Wikibase\Lib\Store\Sql\EntityIdLocalPartPageTableEntityQuery;
use Wikibase\Lib\Store\Sql\PropertyInfoTable;
use Wikibase\Lib\Store\Sql\WikiPageEntityDataLoader;
use Wikibase\Lib\Store\Sql\WikiPageEntityMetaDataLookup;
use Wikibase\Lib\Store\Sql\WikiPageEntityRevisionLookup;
use Wikibase\RebuildTermsSearchKey;
use Wikibase\Repo\Maintenance\PopulateTermFullEntityId;
use Wikibase\Repo\RangeTraversable;
use Wikibase\Repo\Store\ItemTermsRebuilder;
use Wikibase\Repo\Store\PropertyTermsRebuilder;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Store;
use Wikimedia\Rdbms\IDatabase;

/**
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 * @author Marius Hoch
 */
class DatabaseSchemaUpdater {

	/**
	 * @var Store
	 */
	private $store;

	public function __construct( Store $store ) {
		$this->store = $store;
	}

	private static function newFromGlobalState() {
		$store = WikibaseRepo::getDefaultInstance()->getStore();

		return new self( $store );
	}

	/**
	 * Schema update to set up the needed database tables.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 *
	 * @param DatabaseUpdater $updater
	 *
	 * @return bool
	 */
	public static function onSchemaUpdate( DatabaseUpdater $updater ) {
		$schemaUpdater = self::newFromGlobalState();
		$schemaUpdater->doSchemaUpdate( $updater );

		return true;
	}

	public function doSchemaUpdate( DatabaseUpdater $updater ) {
		$db = $updater->getDB();
		$type = $db->getType();

		if ( $type !== 'mysql' && $type !== 'sqlite' ) {
			wfWarn( "Database type '$type' is not supported by the Wikibase repository." );
			return;
		}

		$this->addChangesTable( $updater, $type );

		if ( $db->tableExists( 'wb_aliases' ) ) {
			// Update from 0.1.
			$updater->dropExtensionTable( 'wb_items_per_site' );
			$updater->dropExtensionTable( 'wb_items' );
			$updater->dropExtensionTable( 'wb_aliases' );
			$updater->dropExtensionTable( 'wb_texts_per_lang' );

			$updater->addExtensionTable(
				'wb_items_per_site',
				$this->getUpdateScriptPath( 'Wikibase', $db->getType() )
			);

			$this->store->rebuild();
		} elseif ( !$db->tableExists( 'wb_items_per_site' ) ) {
			// Clean installation
			$updater->addExtensionTable(
				'wb_items_per_site',
				$this->getUpdateScriptPath( 'Wikibase', $db->getType() )
			);

			$this->store->rebuild();
		}

		$this->updateTermsTable( $updater, $db );
		$this->updateItemsPerSiteTable( $updater, $db );
		$this->updateChangesTable( $updater, $db );

		$this->registerPropertyInfoTableUpdates( $updater );

		if ( $db->tableExists( 'wb_entity_per_page' ) ) {
			$updater->dropExtensionTable( 'wb_entity_per_page' );
		}

		$updater->addExtensionTable(
			'wbt_text',
			$this->getUpdateScriptPath( 'AddNormalizedTermsTablesDDL', $db->getType() )
		);
		if ( !$updater->updateRowExists( __CLASS__ . '::rebuildPropertyTerms' ) ) {
			$updater->addExtensionUpdate( [
				[ __CLASS__, 'rebuildPropertyTerms' ]
			] );
		}
		if ( !$updater->updateRowExists( __CLASS__ . '::rebuildItemTerms' ) ) {
			$updater->addExtensionUpdate( [
				[ __CLASS__, 'rebuildItemTerms' ]
			] );
		}
	}

	/**
	 * @param DatabaseUpdater $updater
	 * @param string $type
	 */
	private function addChangesTable( DatabaseUpdater $updater, $type ) {
		$updater->addExtensionTable(
			'wb_changes',
			$this->getUpdateScriptPath( 'changes', $type )
		);

		if ( $type === 'mysql' && !$updater->updateRowExists( 'ChangeChangeObjectId.sql' ) ) {
			$updater->addExtensionUpdate( [
				'applyPatch',
				$this->getUpdateScriptPath( 'ChangeChangeObjectId', $type ),
				true
			] );

			$updater->insertUpdateRow( 'ChangeChangeObjectId.sql' );
		}

		$updater->addExtensionTable(
			'wb_changes_dispatch',
			$this->getUpdateScriptPath( 'changes_dispatch', $type )
		);
	}

	private function updateItemsPerSiteTable( DatabaseUpdater $updater, IDatabase $db ) {
		// Make wb_items_per_site.ips_site_page VARCHAR(310) - T99459
		// NOTE: this update doesn't work on SQLite, but it's not needed there anyway.
		if ( $db->getType() !== 'sqlite' ) {
			$updater->modifyExtensionField(
				'wb_items_per_site',
				'ips_site_page',
				$this->getUpdateScriptPath( 'MakeIpsSitePageLarger', $db->getType() )
			);
		}
		$updater->dropExtensionIndex(
			'wb_items_per_site',
			'wb_ips_site_page',
			$this->getUpdateScriptPath( 'DropItemsPerSiteIndex', $db->getType() )
		);
	}

	private function updateChangesTable( DatabaseUpdater $updater, IDatabase $db ) {
		// Make wb_changes.change_info MEDIUMBLOB - T108246
		// NOTE: this update doesn't work on SQLite, but it's not needed there anyway.
		if ( $db->getType() !== 'sqlite' ) {
			$updater->modifyExtensionField(
				'wb_changes',
				'change_info',
				$this->getUpdateScriptPath( 'MakeChangeInfoLarger', $db->getType() )
			);
		}
	}

	private function registerPropertyInfoTableUpdates( DatabaseUpdater $updater ) {
		$table = 'wb_property_info';

		if ( !$updater->tableExists( $table ) ) {
			$type = $updater->getDB()->getType();
			$fileBase = __DIR__ . '/../../../sql/' . $table;

			$file = $fileBase . '.' . $type . '.sql';
			if ( !file_exists( $file ) ) {
				$file = $fileBase . '.sql';
			}

			$updater->addExtensionTable( $table, $file );

			// populate the table after creating it
			$updater->addExtensionUpdate( [
				[ __CLASS__, 'rebuildPropertyInfo' ]
			] );
		}
	}

	/**
	 * Wrapper for invoking PropertyInfoTableBuilder from DatabaseUpdater
	 * during a database update.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function rebuildPropertyInfo( DatabaseUpdater $updater ) {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$localEntitySourceName = $wikibaseRepo->getSettings()->getSetting( 'localEntitySourceName' );
		$propertySource = $wikibaseRepo
			->getEntitySourceDefinitions()
			->getSourceForEntityType( 'property' );
		if ( $propertySource->getSourceName() !== $localEntitySourceName ) {
			// Foreign properties, skip this part
			return;
		}
		$reporter = new ObservableMessageReporter();
		$reporter->registerReporterCallback(
			function ( $msg ) use ( $updater ) {
				$updater->output( "..." . $msg . "\n" );
			}
		);

		$propertySource = $wikibaseRepo->getEntitySourceDefinitions()->getSourceForEntityType( 'property' );

		$table = new PropertyInfoTable( $wikibaseRepo->getEntityIdComposer(), $propertySource );

		$contentCodec = $wikibaseRepo->getEntityContentDataCodec();
		$propertyInfoBuilder = $wikibaseRepo->newPropertyInfoBuilder();

		$wikiPageEntityLookup = new WikiPageEntityRevisionLookup(
			new WikiPageEntityMetaDataLookup(
				$wikibaseRepo->getEntityNamespaceLookup(),
				new EntityIdLocalPartPageTableEntityQuery(
					$wikibaseRepo->getEntityNamespaceLookup(),
					MediaWikiServices::getInstance()->getSlotRoleStore()
				),
				$propertySource
			),
			new WikiPageEntityDataLoader( $contentCodec, MediaWikiServices::getInstance()->getBlobStore() ),
			MediaWikiServices::getInstance()->getRevisionStore(),
			false
		);

		$cachingEntityLookup = new CachingEntityRevisionLookup(
			new EntityRevisionCache( new HashBagOStuff() ),
			$wikiPageEntityLookup
		);
		$entityLookup = new RevisionBasedEntityLookup( $cachingEntityLookup );

		$builder = new PropertyInfoTableBuilder(
			$table,
			new LegacyAdapterPropertyLookup( $entityLookup ),
			$propertyInfoBuilder,
			$wikibaseRepo->getEntityIdComposer(),
			$wikibaseRepo->getEntityNamespaceLookup()
		);
		$builder->setReporter( $reporter );
		$builder->setUseTransactions( false );

		$updater->output( 'Populating ' . $table->getTableName() . "\n" );
		$builder->rebuildPropertyInfo();
	}

	public static function rebuildPropertyTerms( DatabaseUpdater $updater ) {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$localEntitySourceName = $wikibaseRepo->getSettings()->getSetting( 'localEntitySourceName' );
		$propertySource = $wikibaseRepo
			->getEntitySourceDefinitions()
			->getSourceForEntityType( 'property' );
		if ( $propertySource->getSourceName() !== $localEntitySourceName ) {
			// Foreign properties, skip this part
			return;
		}
		$sqlEntityIdPagerFactory = new SqlEntityIdPagerFactory(
			$wikibaseRepo->getEntityNamespaceLookup(),
			$wikibaseRepo->getEntityIdLookup()
		);
		$reporter = new ObservableMessageReporter();
		$reporter->registerReporterCallback(
			function ( $msg ) use ( $updater ) {
				$updater->output( "..." . $msg . "\n" );
			}
		);

		$rebuilder = new PropertyTermsRebuilder(
			$wikibaseRepo->getNewPropertyTermStoreWriter(),
			$sqlEntityIdPagerFactory->newSqlEntityIdPager( [ 'property' ] ),
			$reporter,
			$reporter,
			MediaWikiServices::getInstance()->getDBLoadBalancerFactory(),
			$wikibaseRepo->getPropertyLookup( Store::LOOKUP_CACHING_RETRIEVE_ONLY ),
			250,
			2
		);

		$rebuilder->rebuild();
		$updater->insertUpdateRow( __CLASS__ . '::rebuildPropertyTerms' );
	}

	public static function rebuildItemTerms( DatabaseUpdater $updater ) {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$localEntitySourceName = $wikibaseRepo->getSettings()->getSetting( 'localEntitySourceName' );
		$itemSource = $wikibaseRepo
			->getEntitySourceDefinitions()
			->getSourceForEntityType( 'item' );
		if ( $itemSource->getSourceName() !== $localEntitySourceName ) {
			// Foreign items, skip this part
			return;
		}
		$reporter = new ObservableMessageReporter();
		$reporter->registerReporterCallback(
			function ( $msg ) use ( $updater ) {
				$updater->output( "..." . $msg . "\n" );
			}
		);

		$highestId = MediaWikiServices::getInstance()
			->getDBLoadBalancer()
			->getConnection( DB_REPLICA )
			->selectRow(
				'wb_id_counters',
				'id_value',
				[ 'id_type' => 'wikibase-item' ],
				__METHOD__
			);
		if ( $highestId === false ) {
			// Fresh instance, no need to rebuild anything
			return;
		}
		$highestId = (int)$highestId->id_value;

		$rebuilder = new ItemTermsRebuilder(
			$wikibaseRepo->getNewItemTermStoreWriter(),
			self::newItemIdIterator( $highestId ),
			$reporter,
			$reporter,
			MediaWikiServices::getInstance()->getDBLoadBalancerFactory(),
			$wikibaseRepo->getItemLookup( Store::LOOKUP_CACHING_RETRIEVE_ONLY ),
			250,
			2
		);

		$rebuilder->rebuild();
		$updater->insertUpdateRow( __CLASS__ . '::rebuildItemTerms' );
	}

	private static function newItemIdIterator( int $highestId ): \Iterator {
		$idRange = new RangeTraversable(
			1,
			$highestId
		);

		foreach ( $idRange as $integer ) {
			yield ItemId::newFromNumber( $integer );
		}
	}

	/**
	 * Returns the script directory that contains a file with the given name.
	 *
	 * @param string $fileName with extension
	 *
	 * @throws MWException If the file was not found in any script directory
	 * @return string The directory that contains the file
	 */
	private function getUpdateScriptDir( $fileName ) {
		$dirs = [
			__DIR__,
			__DIR__ . '/../../../sql'
		];

		foreach ( $dirs as $dir ) {
			if ( file_exists( "$dir/$fileName" ) ) {
				return $dir;
			}
		}

		throw new MWException( "Update script not found: $fileName" );
	}

	/**
	 * Returns the appropriate script file for use with the given database type.
	 * Searches for files with type-specific extensions in the script directories,
	 * falling back to the plain ".sql" extension if no specific script is found.
	 *
	 * @param string $name the script's name, without file extension
	 * @param string $type the database type, as returned by IDatabase::getType()
	 *
	 * @return string The path to the script file
	 * @throws MWException If the script was not found in any script directory
	 */
	private function getUpdateScriptPath( $name, $type ) {
		$extensions = [
			'sqlite' => 'sqlite.sql',
			//'postgres' => 'pg.sql', // PG support is broken as of Dec 2013
			'mysql' => 'mysql.sql',
		];

		// Find the base directory by looking for a plain ".sql" file.
		$dir = $this->getUpdateScriptDir( "$name.sql" );

		if ( isset( $extensions[$type] ) ) {
			$extension = $extensions[$type];
			$path = "$dir/$name.$extension";

			// if a type-specific file exists, use it
			if ( file_exists( "$dir/$name.$extension" ) ) {
				return $path;
			}
		} else {
			throw new MWException( "Database type $type is not supported by Wikibase!" );
		}

		// we already know that the generic file exists
		$path = "$dir/$name.sql";
		return $path;
	}

	/**
	 * Applies updates to the wb_terms table.
	 *
	 * @param DatabaseUpdater $updater
	 * @param IDatabase $db
	 */
	private function updateTermsTable( DatabaseUpdater $updater, IDatabase $db ) {
		// ---- Update from 0.1 or 0.2. ----
		if ( !$db->fieldExists( 'wb_terms', 'term_search_key' ) ) {
			$updater->addExtensionField(
				'wb_terms',
				'term_search_key',
				$this->getUpdateScriptPath( 'AddTermsSearchKey', $db->getType() )
			);

			$updater->addPostDatabaseUpdateMaintenance( RebuildTermsSearchKey::class );
		}

		// creates wb_terms.term_row_id
		// and also wb_item_per_site.ips_row_id.
		$updater->addExtensionField(
			'wb_terms',
			'term_row_id',
			$this->getUpdateScriptPath( 'AddRowIDs', $db->getType() )
		);

		// add weight to wb_terms
		$updater->addExtensionField(
			'wb_terms',
			'term_weight',
			$this->getUpdateScriptPath( 'AddTermsWeight', $db->getType() )
		);

		// ---- Update from 0.4 ----

		// NOTE: this update doesn't work on SQLite, but it's not needed there anyway.
		if ( $db->getType() !== 'sqlite' ) {
			// make term_row_id BIGINT
			$updater->modifyExtensionField(
				'wb_terms',
				'term_row_id',
				$this->getUpdateScriptPath( 'MakeRowIDsBig', $db->getType() )
			);
		}

		// updated indexes
		$updater->dropExtensionIndex(
			'wb_terms',
			'wb_terms_entity_type',
			$this->getUpdateScriptPath( 'DropUnusedTermIndexes', $db->getType() )
		);

		// T159851
		$updater->addExtensionField(
			'wb_terms',
			'term_full_entity_id',
			$this->getUpdateScriptPath( 'AddTermsFullEntityId', $db->getType() )
		);

		$updater->dropExtensionIndex(
			'wb_terms',
			'term_search',
			$this->getUpdateScriptPath( 'DropNotFullEntityIdTermIndexes', $db->getType() )
		);

		// T202265
		$updater->addExtensionIndex(
			'wb_terms',
			'tmp1',
			$this->getUpdateScriptPath( 'AddWbTermsTmp1Index', $db->getType() )
		);

		// T204836
		$updater->addExtensionIndex(
			'wb_terms',
			'wb_terms_entity_id',
			$this->getUpdateScriptPath( 'AddWbTermsEntityIdIndex', $db->getType() )
		);

		// T204837
		$updater->addExtensionIndex(
			'wb_terms',
			'wb_terms_text',
			$this->getUpdateScriptPath( 'AddWbTermsTextIndex', $db->getType() )
		);

		// T204838
		$updater->addExtensionIndex(
			'wb_terms',
			'wb_terms_search_key',
			$this->getUpdateScriptPath( 'AddWbTermsSearchKeyIndex', $db->getType() )
		);

		$updater->addPostDatabaseUpdateMaintenance( PopulateTermFullEntityId::class );
		// TODO: drop old column as now longer needed (but only if all rows got the new column populated!)
	}

}
