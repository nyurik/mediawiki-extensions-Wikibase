<?php

namespace Wikibase\Repo\Store\Sql;

use HashBagOStuff;
use Hooks;
use MediaWiki\MediaWikiServices;
use RequestContext;
use Revision;
use Wikibase\DataAccess\EntitySource;
use Wikibase\DataAccess\WikibaseServices;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\Entity\EntityPrefetcher;
use Wikibase\DataModel\Services\EntityId\EntityIdComposer;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\EntityRedirectLookup;
use Wikibase\DataModel\Services\Lookup\RedirectResolvingEntityLookup;
use Wikibase\Lib\Changes\EntityChangeFactory;
use Wikibase\Lib\Store\CacheAwarePropertyInfoStore;
use Wikibase\Lib\Store\CacheRetrievingEntityRevisionLookup;
use Wikibase\Lib\Store\CachingEntityRevisionLookup;
use Wikibase\Lib\Store\CachingPropertyInfoLookup;
use Wikibase\Lib\Store\EntityByLinkedTitleLookup;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Lib\Store\EntityInfoBuilder;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\EntityRevisionCache;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\EntityStoreWatcher;
use Wikibase\Lib\Store\EntityTermStoreWriter;
use Wikibase\Lib\Store\LabelConflictFinder;
use Wikibase\Lib\Store\LegacyEntityTermStoreReader;
use Wikibase\Lib\Store\PropertyInfoLookup;
use Wikibase\Lib\Store\PropertyInfoStore;
use Wikibase\Lib\Store\RevisionBasedEntityLookup;
use Wikibase\Lib\Store\SiteLinkStore;
use Wikibase\Lib\Store\Sql\EntityChangeLookup;
use Wikibase\Lib\Store\Sql\PrefetchingWikiPageEntityMetaDataAccessor;
use Wikibase\Lib\Store\Sql\PropertyInfoTable;
use Wikibase\Lib\Store\Sql\SiteLinkTable;
use Wikibase\Lib\Store\Sql\TermSqlIndex;
use Wikibase\Lib\Store\TermIndex;
use Wikibase\Lib\Store\TypeDispatchingEntityRevisionLookup;
use Wikibase\Lib\Store\TypeDispatchingEntityStore;
use Wikibase\Lib\StringNormalizer;
use Wikibase\Repo\Store\DispatchingEntityStoreWatcher;
use Wikibase\Repo\Store\EntityTitleStoreLookup;
use Wikibase\Repo\Store\IdGenerator;
use Wikibase\Repo\Store\ItemsWithoutSitelinksFinder;
use Wikibase\Repo\Store\SiteLinkConflictLookup;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Store;
use Wikimedia\Rdbms\DBQueryError;
use WikiPage;

/**
 * Implementation of the store interface using an SQL backend via MediaWiki's
 * storage abstraction layer.
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class SqlStore implements Store {

	/**
	 * @var EntityChangeFactory
	 */
	private $entityChangeFactory;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var EntityIdComposer
	 */
	private $entityIdComposer;

	/**
	 * @var EntityRevisionLookup|null
	 */
	private $entityRevisionLookup = null;

	/**
	 * @var EntityRevisionLookup|null
	 */
	private $rawEntityRevisionLookup = null;

	/**
	 * @var CacheRetrievingEntityRevisionLookup|null
	 */
	private $cacheRetrievingEntityRevisionLookup = null;

	/**
	 * @var EntityStore|null
	 */
	private $entityStore = null;

	/**
	 * @var DispatchingEntityStoreWatcher|null
	 */
	private $entityStoreWatcher = null;

	/**
	 * @var PropertyInfoLookup|null
	 */
	private $propertyInfoLookup = null;

	/**
	 * @var PropertyInfoStore|null
	 */
	private $propertyInfoStore = null;

	/**
	 * @var PropertyInfoTable|null
	 */
	private $propertyInfoTable = null;

	/**
	 * @var TermIndex|LabelConflictFinder|null
	 */
	private $termIndex = null;

	/**
	 * @var PrefetchingWikiPageEntityMetaDataAccessor|null
	 */
	private $entityPrefetcher = null;

	/**
	 * @var EntityIdLookup
	 */
	private $entityIdLookup;

	/**
	 * @var EntityTitleStoreLookup
	 */
	private $entityTitleLookup;

	/**
	 * @var EntityNamespaceLookup
	 */
	private $entityNamespaceLookup;

	/**
	 * @var WikibaseServices
	 */
	private $wikibaseServices;

	/**
	 * @var string
	 */
	private $cacheKeyPrefix;

	/**
	 * @var string
	 */
	private $cacheKeyGroup;

	/**
	 * @var int
	 */
	private $cacheType;

	/**
	 * @var int
	 */
	private $cacheDuration;

	/**
	 * @var IdGenerator
	 */
	private $idGenerator;

	/**
	 * @var bool
	 */
	private $useSearchFields;

	/**
	 * @var bool
	 */
	private $forceWriteSearchFields;

	private $entitySource;

	private $dataAccessSettings;

	/**
	 * @param EntityChangeFactory $entityChangeFactory
	 * @param EntityIdParser $entityIdParser
	 * @param EntityIdComposer $entityIdComposer
	 * @param EntityIdLookup $entityIdLookup
	 * @param EntityTitleStoreLookup $entityTitleLookup
	 * @param EntityNamespaceLookup $entityNamespaceLookup
	 * @param IdGenerator $idGenerator
	 * @param WikibaseServices $wikibaseServices Service container providing data access services
	 * @param EntitySource $entitySource
	 */
	public function __construct(
		EntityChangeFactory $entityChangeFactory,
		EntityIdParser $entityIdParser,
		EntityIdComposer $entityIdComposer,
		EntityIdLookup $entityIdLookup,
		EntityTitleStoreLookup $entityTitleLookup,
		EntityNamespaceLookup $entityNamespaceLookup,
		IdGenerator $idGenerator,
		WikibaseServices $wikibaseServices,
		EntitySource $entitySource
	) {
		$this->entityChangeFactory = $entityChangeFactory;
		$this->entityIdParser = $entityIdParser;
		$this->entityIdComposer = $entityIdComposer;
		$this->entityIdLookup = $entityIdLookup;
		$this->entityTitleLookup = $entityTitleLookup;
		$this->entityNamespaceLookup = $entityNamespaceLookup;
		$this->idGenerator = $idGenerator;
		$this->wikibaseServices = $wikibaseServices;
		$this->entitySource = $entitySource;

		//TODO: inject settings
		$repo = WikibaseRepo::getDefaultInstance();
		$settings = $repo->getSettings();
		$this->cacheKeyPrefix = $settings->getSetting( 'sharedCacheKeyPrefix' );
		$this->cacheKeyGroup = $settings->getSetting( 'sharedCacheKeyGroup' );
		$this->cacheType = $settings->getSetting( 'sharedCacheType' );
		$this->cacheDuration = $settings->getSetting( 'sharedCacheDuration' );
		$this->useSearchFields = $settings->getSetting( 'useTermsTableSearchFields' );
		$this->forceWriteSearchFields = $settings->getSetting( 'forceWriteTermsTableSearchFields' );
		$this->dataAccessSettings = $repo->getDataAccessSettings();
	}

	/**
	 * @see Store::getTermIndex
	 *
	 * If you need a TermIndex implementation for a EntityHandler, when the entity handler
	 * doesn't do anything with the TermIndex please use a NulLTermIndex.
	 *
	 * @depreacted Use getLegacyEntityTermStoreReader, getLegacyEntityTermStoreWriter
	 * or getLabelConflictFinder directly.
	 *
	 * @return TermSqlIndex
	 */
	public function getTermIndex() {
		if ( !$this->termIndex ) {
			$this->termIndex = $this->newTermIndex();
		}

		return $this->termIndex;
	}

	/**
	 * @see Store::getLegacyEntityTermStoreReader
	 *
	 * @deprecated This will stop working once Wikibase migrates away from wb_terms
	 * An exact alternative MAY NOT be available.
	 *
	 * @return LegacyEntityTermStoreReader
	 */
	public function getLegacyEntityTermStoreReader() {
		return $this->getTermIndex();
	}

	/**
	 * @see Store::getLegacyEntityTermStoreWriter
	 *
	 * @deprecated This will stop working once Wikibase migrates away from wb_terms
	 * An alternative will be available
	 *
	 * @return EntityTermStoreWriter
	 */
	public function getLegacyEntityTermStoreWriter() {
		return $this->getTermIndex();
	}

	/**
	 * @see Store::getLabelConflictFinder
	 *
	 * @deprecated This will stop working once Wikibase migrates away from wb_terms
	 * An alternative will be available
	 *
	 * @return LabelConflictFinder
	 */
	public function getLabelConflictFinder() {
		return $this->getTermIndex();
	}

	/**
	 * @return TermSqlIndex
	 */
	private function newTermIndex() {
		//TODO: Get $stringNormalizer from WikibaseRepo?
		//      Can't really pass this via the constructor...
		// TODO: why this creating its own instance? It probably should have the "multi repository/source" one?
		$stringNormalizer = new StringNormalizer();
		$termSqlIndex = new TermSqlIndex(
			$stringNormalizer,
			$this->entityIdParser,
			$this->entitySource
		);
		$termSqlIndex->setUseSearchFields( $this->useSearchFields );
		$termSqlIndex->setForceWriteSearchFields( $this->forceWriteSearchFields );

		return $termSqlIndex;
	}

	/**
	 * @inheritDoc
	 */
	public function clear() {
		$this->newSiteLinkStore()->clear();
		$this->getTermIndex()->clear();
	}

	/**
	 * @inheritDoc
	 */
	public function rebuild() {
		$dbw = wfGetDB( DB_MASTER );

		// TODO: refactor selection code out (relevant for other stores)

		$pages = $dbw->select(
			[ 'page' ],
			[ 'page_id', 'page_latest' ],
			[ 'page_content_model' => WikibaseRepo::getDefaultInstance()->getEntityContentFactory()->getEntityContentModels() ],
			__METHOD__,
			[ 'LIMIT' => 1000 ] // TODO: continuation
		);

		$revLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		$user = RequestContext::getMain()->getUser();

		// FIXME WikiPage::doEditUpdates is deprecated (T249563)
		foreach ( $pages as $pageRow ) {
			$page = WikiPage::newFromID( $pageRow->page_id );
			$revisionRecord = $revLookup->getRevisionById( $pageRow->page_latest );
			$revision = new Revision( $revisionRecord );
			try {
				$page->doEditUpdates( $revision, $user );
			} catch ( DBQueryError $e ) {
				wfLogWarning(
					'editUpdateFailed for ' . $page->getId() . ' on revision ' .
					$revisionRecord->getId() . ': ' . $e->getMessage()
				);
			}
		}
	}

	/**
	 * @see Store::newSiteLinkStore
	 *
	 * @return SiteLinkStore
	 */
	public function newSiteLinkStore() {
		return new SiteLinkTable( 'wb_items_per_site', false );
	}

	/**
	 * @see Store::getEntityByLinkedTitleLookup
	 *
	 * @return EntityByLinkedTitleLookup
	 */
	public function getEntityByLinkedTitleLookup() {
		$lookup = $this->newSiteLinkStore();

		Hooks::run( 'GetEntityByLinkedTitleLookup', [ &$lookup ] );

		return $lookup;
	}

	/**
	 * @see Store::newItemsWithoutSitelinksFinder
	 *
	 * @return ItemsWithoutSitelinksFinder
	 */
	public function newItemsWithoutSitelinksFinder() {
		return new SqlItemsWithoutSitelinksFinder(
			$this->entityNamespaceLookup
		);
	}

	/**
	 * @return EntityRedirectLookup
	 */
	public function getEntityRedirectLookup() {
		return new WikiPageEntityRedirectLookup(
			$this->entityTitleLookup,
			$this->entityIdLookup,
			MediaWikiServices::getInstance()->getDBLoadBalancer()
		);
	}

	/**
	 * @see Store::getEntityLookup
	 * @see SqlStore::getEntityRevisionLookup
	 *
	 * The EntityLookup returned by this method will resolve redirects.
	 *
	 * @param string $cache One of self::LOOKUP_CACHING_*
	 *        @see self::LOOKUP_CACHING_DISABLED to get an uncached direct lookup
	 *        self::LOOKUP_CACHING_RETRIEVE_ONLY to get a lookup which reads from the cache, but doesn't store retrieved entities
	 *        self::LOOKUP_CACHING_ENABLED to get a caching lookup (default)
	 *
	 * @return EntityLookup
	 */
	public function getEntityLookup( $cache = self::LOOKUP_CACHING_ENABLED ) {
		$revisionLookup = $this->getEntityRevisionLookup( $cache );
		$revisionBasedLookup = new RevisionBasedEntityLookup( $revisionLookup );
		$resolvingLookup = new RedirectResolvingEntityLookup( $revisionBasedLookup );
		return $resolvingLookup;
	}

	/**
	 * @see Store::getEntityStoreWatcher
	 *
	 * @return EntityStoreWatcher
	 */
	public function getEntityStoreWatcher() {
		if ( !$this->entityStoreWatcher ) {
			$this->entityStoreWatcher = new DispatchingEntityStoreWatcher();
		}

		return $this->entityStoreWatcher;
	}

	/**
	 * @see Store::getEntityStore
	 *
	 * @return EntityStore
	 */
	public function getEntityStore() {
		if ( !$this->entityStore ) {
			$this->entityStore = $this->newEntityStore();
		}

		return $this->entityStore;
	}

	/**
	 * @return EntityStore
	 */
	private function newEntityStore() {
		$contentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		$store = new WikiPageEntityStore(
			$contentFactory,
			$this->idGenerator,
			$this->entityIdComposer,
			MediaWikiServices::getInstance()->getRevisionStore(),
			$this->entitySource,
			MediaWikiServices::getInstance()->getPermissionManager()
		);
		$store->registerWatcher( $this->getEntityStoreWatcher() );

		$store = new TypeDispatchingEntityStore(
			WikibaseRepo::getDefaultInstance()->getEntityStoreFactoryCallbacks(),
			$store,
			$this->getEntityRevisionLookup( self::LOOKUP_CACHING_DISABLED )
		);

		return $store;
	}

	/**
	 * @see Store::getEntityRevisionLookup
	 *
	 * @param string $cache One of self::LOOKUP_CACHING_*
	 *        self::LOOKUP_CACHING_DISABLED to get an uncached direct lookup
	 *        self::LOOKUP_CACHING_RETRIEVE_ONLY to get a lookup which reads from the cache, but doesn't store retrieved entities
	 *        self::LOOKUP_CACHING_ENABLED to get a caching lookup (default)
	 *
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup( $cache = self::LOOKUP_CACHING_ENABLED ) {
		if ( !$this->entityRevisionLookup ) {
			[ $this->rawEntityRevisionLookup, $this->entityRevisionLookup ] = $this->newEntityRevisionLookup();
		}

		if ( $cache === self::LOOKUP_CACHING_DISABLED ) {
			return $this->rawEntityRevisionLookup;
		} elseif ( $cache === self::LOOKUP_CACHING_RETRIEVE_ONLY ) {
			return $this->getCacheRetrievingEntityRevisionLookup();
		} else {
			return $this->entityRevisionLookup;
		}
	}

	/**
	 * @return string
	 */
	private function getEntityRevisionLookupCacheKey() {
		// NOTE: Keep cache key in sync with DirectSqlStore::newEntityRevisionLookup in WikibaseClient
		return $this->cacheKeyPrefix . ':WikiPageEntityRevisionLookup';
	}

	/**
	 * Creates a strongly connected pair of EntityRevisionLookup services, the first being the
	 * non-caching lookup, the second being the caching lookup.
	 *
	 * @return EntityRevisionLookup[] A two-element array with a "raw", non-caching and a caching
	 *  EntityRevisionLookup.
	 */
	private function newEntityRevisionLookup() {
		// Maintain a list of watchers to be notified of changes to any entities,
		// in order to update caches.
		/** @var WikiPageEntityStore $dispatcher */
		$dispatcher = $this->getEntityStoreWatcher();
		'@phan-var WikiPageEntityStore $dispatcher';

		$dispatcher->registerWatcher( $this->wikibaseServices->getEntityStoreWatcher() );
		$nonCachingLookup = $this->wikibaseServices->getEntityRevisionLookup();

		$nonCachingLookup = new TypeDispatchingEntityRevisionLookup(
			WikibaseRepo::getDefaultInstance()->getEntityRevisionLookupFactoryCallbacks(),
			$nonCachingLookup
		);

		// Lower caching layer using persistent cache (e.g. memcached).
		$persistentCachingLookup = new CachingEntityRevisionLookup(
			new EntityRevisionCache(
				wfGetCache( $this->cacheType ),
				$this->cacheDuration,
				$this->getEntityRevisionLookupCacheKey()
			),
			$nonCachingLookup
		);
		// We need to verify the revision ID against the database to avoid stale data.
		$persistentCachingLookup->setVerifyRevision( true );
		$dispatcher->registerWatcher( $persistentCachingLookup );

		// Top caching layer using an in-process hash.
		$hashCachingLookup = new CachingEntityRevisionLookup(
			new EntityRevisionCache( new HashBagOStuff( [ 'maxKeys' => 1000 ] ) ),
			$persistentCachingLookup
		);
		// No need to verify the revision ID, we'll ignore updates that happen during the request.
		$hashCachingLookup->setVerifyRevision( false );
		$dispatcher->registerWatcher( $hashCachingLookup );

		return [ $nonCachingLookup, $hashCachingLookup ];
	}

	/**
	 * @return CacheRetrievingEntityRevisionLookup
	 */
	private function getCacheRetrievingEntityRevisionLookup() {
		if ( !$this->cacheRetrievingEntityRevisionLookup ) {
			$cacheRetrievingEntityRevisionLookup = new CacheRetrievingEntityRevisionLookup(
				new EntityRevisionCache(
					wfGetCache( $this->cacheType ),
					$this->cacheDuration,
					$this->getEntityRevisionLookupCacheKey()
				),
				$this->getEntityRevisionLookup( self::LOOKUP_CACHING_DISABLED )
			);

			$cacheRetrievingEntityRevisionLookup->setVerifyRevision( true );

			$this->cacheRetrievingEntityRevisionLookup = $cacheRetrievingEntityRevisionLookup;
		}

		return $this->cacheRetrievingEntityRevisionLookup;
	}

	/**
	 * @see Store::getEntityInfoBuilder
	 *
	 * @return EntityInfoBuilder
	 */
	public function getEntityInfoBuilder() {
		return $this->wikibaseServices->getEntityInfoBuilder();
	}

	/**
	 * @see Store::getPropertyInfoLookup
	 *
	 * @return PropertyInfoLookup
	 */
	public function getPropertyInfoLookup() {
		if ( !$this->propertyInfoLookup ) {
			$this->propertyInfoLookup = $this->newPropertyInfoLookup();
		}

		return $this->propertyInfoLookup;
	}

	/**
	 * Note: cache key used by the lookup should be the same as the cache key used
	 * by CachedPropertyInfoStore.
	 *
	 * @return PropertyInfoLookup
	 */
	private function newPropertyInfoLookup() {
		return new CachingPropertyInfoLookup(
			$this->wikibaseServices->getPropertyInfoLookup(),
			MediaWikiServices::getInstance()->getMainWANObjectCache(),
			$this->cacheKeyGroup,
			$this->cacheDuration
		);
	}

	/**
	 * @see Store::getPropertyInfoStore
	 *
	 * @return PropertyInfoStore
	 */
	public function getPropertyInfoStore() {
		if ( !$this->propertyInfoStore ) {
			$this->propertyInfoStore = $this->newPropertyInfoStore();
		}

		return $this->propertyInfoStore;
	}

	/**
	 * Creates a new PropertyInfoStore
	 * Note: cache key used by the lookup should be the same as the cache key used
	 * by CachedPropertyInfoLookup.
	 *
	 * @return PropertyInfoStore
	 */
	private function newPropertyInfoStore() {
		// TODO: this should be changed so it uses the same PropertyInfoTable instance which is used by
		// the lookup configured for local repo in DispatchingPropertyInfoLookup (if using dispatching services
		// from client). As we don't want to introduce DispatchingPropertyInfoStore service, this should probably
		// be accessing RepositorySpecificServices of local repo (which is currently not exposed
		// to/by WikibaseClient).
		// For non-dispatching-service use case it is already using the same PropertyInfoTable instance
		// for both store and lookup - no change needed here.

		$table = $this->getPropertyInfoTable();

		// TODO: we might want to register the CacheAwarePropertyInfoLookup instance created by
		// newPropertyInfoLookup as a watcher to this CacheAwarePropertyInfoStore instance.
		return new CacheAwarePropertyInfoStore(
			$table,
			MediaWikiServices::getInstance()->getMainWANObjectCache(),
			$this->cacheDuration,
			$this->cacheKeyGroup
		);
	}

	/**
	 * @return PropertyInfoTable
	 */
	private function getPropertyInfoTable() {
		if ( $this->propertyInfoTable === null ) {
			$this->propertyInfoTable = new PropertyInfoTable( $this->entityIdComposer, $this->entitySource );
		}
		return $this->propertyInfoTable;
	}

	/**
	 * @return SiteLinkConflictLookup
	 */
	public function getSiteLinkConflictLookup() {
		return new SqlSiteLinkConflictLookup( $this->entityIdComposer );
	}

	/**
	 * @return PrefetchingWikiPageEntityMetaDataAccessor
	 */
	public function getEntityPrefetcher() {
		if ( $this->entityPrefetcher === null ) {
			$this->entityPrefetcher = $this->newEntityPrefetcher();
		}

		return $this->entityPrefetcher;
	}

	/**
	 * @return EntityPrefetcher
	 */
	private function newEntityPrefetcher() {
		return $this->wikibaseServices->getEntityPrefetcher();
	}

	/**
	 * @return EntityChangeLookup
	 */
	public function getEntityChangeLookup() {
		return new EntityChangeLookup( $this->entityChangeFactory, $this->entityIdParser );
	}

	/**
	 * @return SqlChangeStore
	 */
	public function getChangeStore() {
		return new SqlChangeStore( MediaWikiServices::getInstance()->getDBLoadBalancer() );
	}

}
