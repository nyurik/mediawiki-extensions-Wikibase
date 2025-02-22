<?php

/**
 * This file assigns the default values to all Wikibase Repo settings.
 *
 * This file is NOT an entry point the Wikibase extension. Use Wikibase.php.
 * It should furthermore not be included from outside the extension.
 *
 * @license GPL-2.0-or-later
 */

global $wgCdnMaxAge;

return [
	// feature flag for federated properties
	'federatedPropertiesEnabled' => false,

	// url for federated properties source location
	'federatedPropertiesSourceScriptUrl' => 'https://www.wikidata.org/w/',

	// feature flag for tainted references
	'taintedReferencesEnabled' => false,

	// url of (termbox) ssr-server
	'ssrServerUrl' => '',

	// Timeout for SSR-Server in seconds
	'ssrServerTimeout' => 3,

	// feature flag for termbox
	'termboxEnabled' => false,

	// debug flag for termbox ssr
	'termboxUserSpecificSsrEnabled' => true,

	'idBlacklist' => [],

	// List of supported entity types, mapping entity type identifiers to namespace IDs.
	// This setting is used to enable entity types.
	'entityNamespaces' => [],

	// List of entity types that (temporarily) can not be changed; identifiers per EntityDocument::getType()
	'readOnlyEntityTypes' => [],

	// See StatementGrouperBuilder for an example.
	'statementSections' => [],

	// Define constraints for various strings, such as multilingual terms (such as labels, descriptions and aliases).
	'string-limits' => [
		'multilang' => [
			'length' => 250, // length constraint
		],
		'VT:monolingualtext' => [
			'length' => 400,
		],
		'VT:string' => [
			'length' => 400,
		],
		'PT:url' => [
			'length' => 500,
		],
	],

	// URL schemes allowed for URL values. See UrlSchemeValidators for a full list.
	'urlSchemes' => [ 'bzr', 'cvs', 'ftp', 'git', 'http', 'https', 'irc', 'mailto', 'ssh', 'svn' ],

	// Items allowed to be used as badges pointing to their CSS class names
	'badgeItems' => [],

	// Number of seconds for which data output on Special:EntityData should be cached.
	// Note: keep that low, because such caches cannot always be purged easily.
	'dataCdnMaxAge' => $wgCdnMaxAge,

	// list of logical database names of local client wikis.
	// may contain mappings from site-id to db-name.
	'localClientDatabases' => [],

	// Settings for change dispatching
	'dispatchMaxTime' => 60 * 60,
	'dispatchIdleDelay' => 10,
	'dispatchBatchChunkFactor' => 3,
	'dispatchBatchCacheFactor' => 3,
	'dispatchDefaultBatchSize' => 1000,
	'dispatchDefaultMaxChunks' => 15,
	'dispatchDefaultDispatchInterval' => 60,
	'dispatchDefaultDispatchRandomness' => 15,

	// Formats that shall be available via Special:EntityData.
	// The first format will be used as the default.
	// This is a whitelist, some formats may not be supported because when missing
	// optional dependencies (e.g. purtle).
	// The formats are given using logical names as used by EntityDataSerializationService.
	'entityDataFormats' => [
		// using the API
		'json', // default
		'php',

		// using purtle
		'rdfxml',
		'n3',
		'turtle',
		'ntriples',
		'jsonld',

		// hardcoded internal handling
		'html',
	],

	'enableEntitySearchUI' => true,

	'dataRightsUrl' => function() {
		return $GLOBALS['wgRightsUrl'];
	},

	'rdfDataRightsUrl' => 'http://creativecommons.org/publicdomain/zero/1.0/',

	'dataRightsText' => function() {
		return $GLOBALS['wgRightsText'];
	},

	'sparqlEndpoint' => null,

	'transformLegacyFormatOnExport' => true,

	'conceptBaseUri' => function() {
		$uri = preg_replace( '!^//!', 'http://', $GLOBALS['wgServer'] );
		return $uri . '/entity/';
	},

	// Property used as formatter to link identifiers
	'formatterUrlProperty' => null,

	// Property used as formatter to link identifiers in JSON/RDF
	'canonicalUriProperty' => null,

	'allowEntityImport' => false,

	/**
	 * Prefix to use for cache keys that should be shared among a Wikibase Repo instance and all
	 * its clients. This is for things like caching entity blobs in memcached.
	 *
	 * The default setting assumes Wikibase Repo + Client installed together on the same wiki.
	 * For a multiwiki / wikifarm setup, to configure shared caches between clients and repo,
	 * this needs to be set to the same value in both client and repo wiki settings.
	 *
	 * For Wikidata production, we set it to 'wikibase-shared/wikidata_1_25wmf24-wikidatawiki',
	 * which is 'wikibase_shared/' + deployment branch name + '-' + repo database name, and have
	 * it set in both $wgWBClientSettings and $wgWBRepoSettings.
	 *
	 * Please note that $wgWBClientSettings overrides settings such as this one in the repo, if
	 * client is enabled on the same wiki.
	 */
	'sharedCacheKeyPrefix' => 'wikibase_shared/' . rawurlencode( WBL_VERSION ) . '-' . $GLOBALS['wgDBname'],
	'sharedCacheKeyGroup' => $GLOBALS['wgDBname'],

	/**
	 * The duration of the object cache, in seconds.
	 *
	 * As with sharedCacheKeyPrefix, this is both client and repo setting. On a multiwiki setup,
	 * this should be set to the same value in both the repo and clients. Also note that the
	 * setting value in $wgWBClientSettings overrides the one here.
	 */
	'sharedCacheDuration' => 60 * 60,

	// The type of object cache to use. Use CACHE_XXX constants.
	// This is both a repo and client setting, and should be set to the same value in
	// repo and clients for multiwiki setups.
	'sharedCacheType' => $GLOBALS['wgMainCacheType'],

	/**
	 * List of data types (by data type id) not enabled on the wiki.
	 * This setting is intended to aid with deployment of new data types
	 * or on new Wikibase installs without items and properties yet.
	 *
	 * This setting should be consistent with the corresponding setting on the client.
	 *
	 * WARNING: Disabling a data type after it is in use is dangerous
	 * and might break items.
	 */
	'disabledDataTypes' => [],

	// Special non-canonical languages and their BCP 47 mappings
	// Based on: https://meta.wikimedia.org/wiki/Special_language_codes
	'canonicalLanguageCodes' => [
			'simple'      => 'en-simple',
			'crh'         => 'crh-Latn',
			'cbk-zam'     => 'cbk-x-zam',
			'map-bms'     => 'jv-x-bms',
			'nrm'         => 'fr-x-nrm',
			'roa-tara'    => 'it-x-tara',
			'de-formal'   => 'de-x-formal',
			'es-formal'   => 'es-x-formal',
			'hu-formal'   => 'hu-x-formal',
			'nl-informal' => 'nl-x-informal',
	],

	// List of image property id strings, in order of preference, that should be considered for
	// the "page_image" page property.
	'preferredPageImagesProperties' => [],

	// List of globe-coordinate property id strings, in order of preference, to consider for
	// primary coordinates when extracting coordinates from an entity for the GeoData extension.
	'preferredGeoDataProperties' => [],

	// Mapping of globe URIs to canonical names, as recognized and used by GeoData extension
	// when indexing and querying for coordinates.
	'globeUris' => [
		'http://www.wikidata.org/entity/Q2' => 'earth',
		'http://www.wikidata.org/entity/Q308' => 'mercury',
		'http://www.wikidata.org/entity/Q313' => 'venus',
		'http://www.wikidata.org/entity/Q405' => 'moon',
		'http://www.wikidata.org/entity/Q111' => 'mars',
		'http://www.wikidata.org/entity/Q7547' => 'phobos',
		'http://www.wikidata.org/entity/Q7548' => 'deimos',
		'http://www.wikidata.org/entity/Q3169' => 'ganymede',
		'http://www.wikidata.org/entity/Q3134' => 'callisto',
		'http://www.wikidata.org/entity/Q3123' => 'io',
		'http://www.wikidata.org/entity/Q3143' => 'europa',
		'http://www.wikidata.org/entity/Q15034' => 'mimas',
		'http://www.wikidata.org/entity/Q3303' => 'enceladus',
		'http://www.wikidata.org/entity/Q15047' => 'tethys',
		'http://www.wikidata.org/entity/Q15040' => 'dione',
		'http://www.wikidata.org/entity/Q15050' => 'rhea',
		'http://www.wikidata.org/entity/Q2565' => 'titan',
		'http://www.wikidata.org/entity/Q15037' => 'hyperion',
		'http://www.wikidata.org/entity/Q17958' => 'iapetus',
		'http://www.wikidata.org/entity/Q17975' => 'phoebe',
		'http://www.wikidata.org/entity/Q3352' => 'miranda',
		'http://www.wikidata.org/entity/Q3343' => 'ariel',
		'http://www.wikidata.org/entity/Q3338' => 'umbriel',
		'http://www.wikidata.org/entity/Q3322' => 'titania',
		'http://www.wikidata.org/entity/Q3332' => 'oberon',
		'http://www.wikidata.org/entity/Q3359' => 'triton',
		'http://www.wikidata.org/entity/Q339' => 'pluto'
	],

	// Map between page properties and Wikibase predicates
	// Maps from database property name to array:
	// name => RDF property name (will be prefixed by wikibase:)
	// type => type to convert to (optional)
	'pagePropertiesRdf' => [
		'wb-sitelinks' => [ 'name' => 'sitelinks', 'type' => 'integer' ],
		'wb-claims' => [ 'name' => 'statements', 'type' => 'integer' ],
		'wb-identifiers' => [ 'name' => 'identifiers', 'type' => 'integer' ],
	],

	// Map of foreign repository names to repository-specific settings such as "supportedEntityTypes"
	'foreignRepositories' => [],

	// URL of geo shape storage API endpoint
	'geoShapeStorageApiEndpointUrl' => 'https://commons.wikimedia.org/w/api.php',

	// Base URL of geo shape storage frontend. Used primarily to build links to the geo shapes. Will
	// be concatenated with the page title, so should end with "/" or "title=". Special characters
	// (e.g. space, percent, etc.) should NOT be encoded.
	'geoShapeStorageBaseUrl' => 'https://commons.wikimedia.org/wiki/',

	// URL of tabular data storage API endpoint
	'tabularDataStorageApiEndpointUrl' => 'https://commons.wikimedia.org/w/api.php',

	// Base URL of tabular data storage frontend. Used primarily to build links to the tabular data
	// pages. Will be concatenated with the page title, so should end with "/" or "title=". Special
	// characters (e.g. space, percent, etc.) should NOT be encoded.
	'tabularDataStorageBaseUrl' => 'https://commons.wikimedia.org/wiki/',

	// Name of the lock manager for dispatch changes coordinator
	'dispatchingLockManager' => null,

	// List of properties to be indexed
	'searchIndexProperties' => [],
	// List of property types to be indexed
	'searchIndexTypes' => [],
	// List of properties to be excluded from indexing
	'searchIndexPropertiesExclude' => [],
	// List of properties that, if in a qualifier, will be used for indexing quantities
	'searchIndexQualifierPropertiesForQuantity' => [],

	// Use search-related fields of wb_terms table
	'useTermsTableSearchFields' => true,

	// Override useTermsTableSearchFields for writing
	'forceWriteTermsTableSearchFields' => false,

	// Change it to a positive number so it becomes effective
	'dispatchLagToMaxLagFactor' => 0,

	// DB group to use in dump maintenance scripts. Defaults to "dump", per T147169.
	'dumpDBDefaultGroup' => 'dump',

	'useKartographerGlobeCoordinateFormatter' => false,

	'useKartographerMaplinkInWikitext' => false,

	// Temporary, see: T199197
	'enableRefTabs' => false,

	/**
	 * The default for this idGenerator will have to remain using the 'original'
	 * generator as the 'upsert' generator only supports MySQL currently.
	 *
	 * @var string 'original' or 'mysql-upsert' depending on what implementation of IdGenerator
	 * you wish to use.
	 *
	 * @see \Wikibase\Repo\WikibaseRepo::newIdGenerator
	 */
	'idGenerator' => 'original',

	/**
	 * Whether use a separate master database connection to generate new id or not.
	 *
	 * @var bool
	 * @see https://phabricator.wikimedia.org/T213817
	 * @see \Wikibase\Repo\WikibaseRepo::newIdGenerator
	 */
	'idGeneratorSeparateDbConnection' => false,

	'entityTypesWithoutRdfOutput' => [],

	'entitySources' => [],

	'localEntitySourceName' => 'local',

	/**
	 * @note This config options is primarily added for Wikidata transition use-case and can be
	 * considered temporary. It could be removed in the future with no warning.
	 *
	 * @var bool Whether to serialize empty containers as {} instead of [] in json output of Special:EntityData
	 *
	 * @see \Wikibase\Repo\LinkedData\EntityDataSerializationService::serializeEmptyContainersProperly
	 */
	'tmpSerializeEmptyListsAsObjects' => true,

	// Do not enable this one in production environments, unless you know what you are doing when
	// using the script there.
	'enablePopulateWithRandomEntitiesAndTermsScript' => false,

	// Namespace id for entity schema data type
	'entitySchemaNamespace' => 640,

	'dataBridgeEnabled' => false,
];
