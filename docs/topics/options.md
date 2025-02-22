# Options

This document describes the configuration of the Wikibase components.

As usual, the extension is configured in MediaWiki's LocalSettings.php file.
However, Wikibase settings are placed in associative arrays, `$wgWBRepoSettings` and `$wgWBClientSettings` respectively, instead of individual global variables.

So, if the setting `foo` is described below, you would need to use ```$wgWBRepoSettings['foo']``` or ```$wgWBClientSettings['foo']``` in LocalSettings.php.

Default settings in each Wikibase settings array are setup buy loading WikibaseLib.default.php followed by the default settings file for either:
 - Wikibase.default.php (for Repos)
 - WikibaseClient.default.php (For Clients)

[TOC]

Common Settings
----------------------------------------------------------------------------------------

Common settings exist on both a Repo and the Client.

### Sitelinks

#### siteLinkGroups {#common_siteLinkGroups}
The site groups to use in sitelinks.

 - Must correspond to a value used to give the site group in the MediaWiki `sites` table.
 - This defines which groups of sites can be linked to Wikibase items.

DEFAULT: is ```[]``` (This defines which groups of sites can be linked to Wikibase items.)

EXAMPLE: ```[ 'wikipedia', 'wikibooks', 'special' ]```

#### specialSiteLinkGroups
This maps one or more site groups into a single “special” group.

This is useful if sites from multiple site groups should be shown in a single “special” section on item pages, instead of one section per site group.
To show these site-groups you have to add the group “special” to the [siteLinkGroups] setting.

EXAMPLE: ```[ 'commons', 'meta', 'wikidata' ]```

### Change Propagation

See @ref md_docs_topics_change-propagation

#### useChangesTable
Whether to record changes in the database, so they can be pushed to clients.

Boolean, may be set to `false` in situations where there are no clients to notify to preserve space.
If this is `true`, the pruneChanges.php script should run periodically to remove old changes from the database table.

DEFAULT: ```true```

#### changesDatabase {#changesDatabase}
The database that changes are recorded to by a repo for processing by clients.

 - This must be set to a symbolic database identifier that MediaWiki's LBFactory class understands; `false` means that the wiki's own database shall be used.
 - On the client this is normally the same database as the repo db.

### Storage URLs

#### geoShapeStorageBaseUrl
Base URL of geo shape storage frontend.

Used primarily to build links to the geo shapes.
Will be concatenated with the page title, so should end with `/` or `title=`.
Special characters (e.g. space, percent, etc.) should *not* be encoded.

DEFAULT: ```"https://commons.wikimedia.org/wiki/"```

### geoShapeStorageApiEndpointUrl

DEFAULT: ```"https://commons.wikimedia.org/w/api.php"```

#### tabularDataStorageBaseUrl
Base URL of tabular data storage frontend.

Used primarily to build links to the tabular data pages.
Will be concatenated with the page title, so should end with `/` or `title=`.
Special characters (e.g. space, percent, etc.) should *not* be encoded.

DEFAULT: ```"https://commons.wikimedia.org/wiki/"```

### tabularDataStorageApiEndpointUrl

DEFAULT: ```"https://commons.wikimedia.org/w/api.php"```

### Shared cache

#### sharedCacheKeyGroup
Group name for a group of Wikibases.

Similar to [sharedCacheKeyPrefix] and normally a part of [sharedCacheKeyPrefix], however this shared cache key group should be used as a part of keys generated within Wikibase.

DEFAULT: Constructed from [$wgDBname].

#### sharedCacheKeyPrefix {#common_sharedCacheKeyPrefix}
Prefix to use for cache keys that should be shared among a wikibase repo and all its clients.

In order to share caches between clients (and the repo), set a prefix based on the repo's name and `WBL_VERSION` or a similar version ID.

DEFAULT: Constructed from [$wgDBname] and WBL_VERSION.

#### sharedCacheDuration
The duration of entries in the shared object cache, in seconds.

DEFAULT: 3600 seconds (1 hour).

#### sharedCacheType
The type of cache to use for the shared object cache. Use `CACHE_XXX` constants.

DEFAULT: [$wgMainCacheType]

### wb_terms

The [wb_terms] table is DEPRECATED and will be removed in the future.

#### useTermsTableSearchFields {#common_useTermsTableSearchFields}
Whether to use the search-related fields (`term_search_key` and `term_weight`) of the [wb_terms] table.

This should not be disabled unless some other search backend is used.

#### forceWriteTermsTableSearchFields
If true, write search-related fields of the [wb_terms] table even if they are not used.

Useful if you want to experiment with [useTermsTableSearchFields] and don’t want missed updates in the table.

### Miscellaneous

#### entitySources {#common_entitySources}
An associative array mapping entity source names to settings relevant to the particular source.

DEFAULT: Populated with a local default from existing settings:
 - [entityNamespaces](#entityNamespaces)
 - [changesDatabase](#changesDatabase)
 - [conceptBaseUri](#conceptBaseUri)
And with foreign repos using [foreignRepositories]{#foreignRepositories}

Configuration of each source is an associative array containing the following keys:

 - `entityNamespaces`: A map of entity type identifiers (strings) that the local wiki supports from the foreign repository to namespaces (IDs or canonical names) related to pages of entities of the given type on foreign repository's wiki. If entities are stored in alternative slots, the syntax ```<namespace>/<slot>``` can be used to define which slot to use.
 - `repoDatabase`: A symbolic database identifier (string) that MediaWiki's LBFactory class understands. Note that `false` would mean “this wiki's database”.
 - `baseUri`: A base URI (string) for concept URIs. It should contain scheme and authority part of the URI.
 - `interwikiPrefix`: An interwiki prefix configured in the local wiki referring to the wiki related to the entity source.
 - `rdfNodeNamespacePrefix`: A prefix used in RDF turtle node namespaces, e.g. 'wd' would result in namespaces like 'wd' for the entity namespace, and 'wdt' for the direct claim namespace, whereas 'sdc' prefix would result in the namespaces 'sdc' and 'sdct' accordingly.
 - `rdfPredicateNamespacePrefix`: A prefix used in RDF turtle predicate namespaces, e.g. '' would result in namespaces like 'ps' for the simple value claim namespace, whereas 'sdc' prefix would result in the namespace 'sdcps'.

#### localEntitySourceName
Name of the entity source name of the "local" repo, i.e. the repo of / on the local wiki.

Should be the name of the entity source defined in [entitySources] setting.

DEFAULT: ```local```

#### disabledDataTypes
Array listing of disabled data types on a wiki.

This setting is intended to be used in a new Wikibase installation without items yet, or to control deployment of new data types.
This setting should be set to the same value in both client and repo settings.

DEFAULT: ```[]``` (empty array)

#### maxSerializedEntitySize
The maximum serialized size of entities, in KB.

Loading and storing will fail if this size is exceeded.
This is intended as a hard limit that prevents very large chunks of data being stored or processed due to abuse or erroneous code.

DEFAULT: [$wgMaxArticleSize]

### useKartographerGlobeCoordinateFormatter

DEFAULT: ```false```

### useKartographerMaplinkInWikitext

DEFAULT: ```false```

Repository Settings
----------------------------------------------------------------------------------------

### Urls, URIs & Paths

#### dataRightsUrl
URL to link to license for data contents.

DEFAULT: [$wgRightsUrl]

#### rdfDataRightsUrl
URL to link to license in RDF outputs.

DEFAULT: ```http://creativecommons.org/publicdomain/zero/1.0/``` (Public domain)

#### sparqlEndpoint
URL to the service description of the SPARQL end point for the repository.

DEFAULT: ````null```` (There is no SPARQL endpoint.)

EXAMPLE: ```https://query.wikidata.org/sparql```

#### conceptBaseUri {#conceptBaseUri}
Base URI for building concept URIs (used in Rdf output).

This has to include the protocol and domain, only an entity identifier will be appended.

DEFAULT: Constructed from [$wgServer] with http protocol and /entity/ path.

EXAMPLE: ```http://www.wikidata.org/entity/```

#### globeUris
Mapping of globe URIs to canonical names, as recognized and used by [GeoData] extension when indexing and querying for coordinates.

EXAMPLE: ```['http://www.wikidata.org/entity/Q2' => 'earth']```

### Properties & Items

#### idGenerator {#repo_idGenerator}
Allows the entity id generator to be chosen. (See @ref md_docs_storage_id-counters)

DEFAULT: ```original```

Allows values: `original` or `mysql-upsert`

#### idGeneratorSeparateDbConnection {#repo_idGeneratorSeparateDbConnection}
Should a separate DB connection be used to generate entity IDs?  (See @ref md_docs_storage_id-counters)

DEFAULT: ```false```

#### badgeItems
Items allowed to be used as badges.

This setting expects an array of serialized item IDs pointing to their CSS class names.
With this class name it is possible to change the icon of a specific badge.

EXAMPLE: ```[ 'Q101' => 'wb-badge-goodarticle' ]```

#### preferredPageImagesProperties
List of image property ID strings, in order of preference, that should be considered for the `page_image` [page property].

DEFAULT: ```[]``` (An empty array.)

EXAMPLE: ```[ 'P10', 'P123', 'P8000' ]```

#### preferredGeoDataProperties
List of properties (by ID string), in order of preference, that are considered when finding primary coordinates for the GeoData extension on an entity.

DEFAULT: ```[]``` (An empty array.)

#### formatterUrlProperty
Property to be used on properties that defines a formatter URL which is used to link external identifiers.

The placeholder `$1` will be replaced by the identifier.
When formatting identifiers, each identifier's property page is checked for its formatter URL (e.g. `http://d-nb.info/gnd/$1`) specified by the property from this setting.

EXAMPLE: On wikidata.org, this is set to `P1630`, a string property named “formatter URL”.

#### canonicalUriProperty
Property to be used on properties that defines a URI pattern which is used to link external identifiers in RDF and other exports. The placeholder `$1` will be replaced by the identifier.

When exporting identifiers to RDF or other formats, each identifier's property page is checked for its URI pattern (e.g. `http://d-nb.info/gnd/$1/about/rdf`) specified by the property from this setting.

EXAMPLE: On wikidata.org, this is set to `P1921`, a string property named “URI used in RDF”.

### Dispatching

#### dispatchingLockManager {#repo_dispatchingLockManager}
If you want to use another lock mechanism for dispatching changes to clients instead of database locking (which can occupy too many connections to the master database), set its name in this config.

See [$wgLockManagers] documentation in MediaWiki core for more information on configuring a locking mechanism inside core.

#### dispatchLagToMaxLagFactor {#repo_dispatchLagToMaxLagFactor}
If set to a positive number, the median dispatch lag (in seconds) will be divided by this number and passed to core like database lag (see the API maxlag parameter).

DEFAULT: ```0``` (disabled)

#### dispatchBatchChunkFactor
Chunk factor used internally by the dispatchChanges.php script.

If most clients are not interested in most changes, this factor can be raised to lower the number of database queries needed to fetch a batch of changes.

DEFAULT: ```3```

#### dispatchDefaultBatchSize
Overrides the default value for batch-size in dispatchChanges.php

DEFAULT: ```1000```

#### dispatchDefaultMaxChunks
Overrides the default value for max-chunks in dispatchChanges.php

DEFAULT: ```15```

#### dispatchDefaultDispatchInterval
Overrides the default value for dispatch-interval in dispatchChanges.php in seconds.

DEFAULT: ```60```

#### dispatchDefaultDispatchRandomness
Overrides the default value for randomness in dispatchChanges.php

DEFAULT: ```15```

#### dispatchMaxTime
Overrides the default value for max-time in dispatchChanges.php in seconds.

DEFAULT: ```3600``` (1 hour)

#### dispatchIdleDelay
Overrides the default value for idle-delay in dispatchChanges.php in seconds.

DEFAULT: ```10```

#### localClientDatabases {#client_localClientDatabases}
An array of locally accessible client databases, for use by the dispatchChanges.php script.

See @ref md_docs_topics_change-propagation
This setting determines to which wikis changes are pushed directly.
It must be given either as an associative array, mapping global site IDs to logical database names, or, of the database names are the same as the site IDs, as a list of databases.

DEFAULT: ```[]``` (An empty array, indicating no local client databases.)

Wikidata has all client sites listed in this array.

### Import, Export & Dumps

#### transformLegacyFormatOnExport
Whether entity revisions stored in a legacy format should be converted on the fly while exporting.

DEFAULT: ```true```

#### allowEntityImport
Allow importing entities via Special:Import and importDump.php.

Per default, imports are forbidden, since entities defined in another wiki would have or use IDs that conflict with entities defined locally.

DEFAULT: ```false```

#### pagePropertiesRdf
Array that maps between [page property] values and Wikibase predicates for RDF dumps.

Maps from database property name to an array that contains a key `'name'` (RDF property name, which will be prefixed by `wikibase:`) and an optional key `'type'`.

#### dumpDBDefaultGroup
This is the default database group to use in dump maintenance scripts, it defaults to `dump`.
Set to `null` to use the value from [$wgDBDefaultGroup].

DEFAULT: ```dump```

#### entityTypesWithoutRdfOutput
Array of entity type names which are not available to be output as RDF.

DEFAULT: ```[]``` (meaning RDF is available for all entity types)

#### entityDataFormats
Formats that shall be available via SpecialEntityData.

The first format will be used as the default.
This is a whitelist, some formats may not be supported because when missing optional dependencies (e.g. purtle).
The formats are given using logical names as used by EntityDataSerializationService.

#### dataCdnMaxAge
Number of seconds for which data output on Special:EntityData should be cached.

Note: keep that low, because such caches cannot always be purged easily.

DEFAULT: [$wgCdnMaxAge]

### Search

#### enableEntitySearchUI
Boolean to determine if entity search UI should be enabled or not.

This overrides the behaviour of the default search box UI in MediaWiki.

DEFAULT: ```true```

#### searchIndexProperties
Array of properties (by ID string) that should be included in the `statement_keywords` field of the search index.

Relevant only for search engines supporting it.

#### searchIndexTypes
Array of auto-indexed type names.

Statements with properties of this type will automatically be indexed in the `statement_keywords` field.

Relevant only for search engines supporting it.

#### searchIndexPropertiesExclude
Array of properties (by ID string) that should be excluded from the `statement_keywords` field.

This takes priority over other searchIndex\* settings.

Relevant only for search engines supporting it.

#### searchIndexQualifierPropertiesForQuantity
Array of properties (by ID string) that, if used in a qualifier, will be used to write a value to the `'statement_quantity'` field.

Relevant only for search engines supporting it.

### Termbox & SSR

#### termboxEnabled {#repo_termboxEnabled}
Enable/Disable Termbox v2. Setting it to ```true``` will enable both client-side and server-side rendering functionality. In order for server-side rendering to work the respective service needs to be set up and ```ssrServerUrl``` has to be set accordingly.

DEFAULT: ```false``` (so all Termbox v2 functionality is disabled)

#### ssrServerUrl
The url to where the server-side-renderer server (for termbox) is running.

#### ssrServerTimeout
Time after which wikibase aborts the connection to the ssr server.

DEFAULT: ```3```

#### termboxUserSpecificSsrEnabled

Enable/Disable server-side rendering (SSR) for user-specific termbox markup.

DEFAULT: ```true```

It only comes into effect if the general [termboxEnabled] is `true`.
If disabled, user-specific termbox markup will only be created by client-side rendering after initial displaying of the generic termbox markup.

### Miscellaneous

#### dataRightsText
Text for data license link.

DEFAULT: [$wgRightsText]

#### statementSections
Configuration to group statements together based on their datatype or other criteria like "propertySet". For example, putting all of external identifiers in one place.

EXAMPLE:
```
$wgWBRepoSettings['statementSections'] = [
	'item' => [
		'statements' => null,
		'identifiers' => [
			'type' => 'dataType',
			'dataTypes' => [ 'external-id' ],
		],
	],
];
```
Section configurations other than "statements" and "identifiers" require you to define `wikibase-statementsection-*` messages for section headings to be rendered correctly.

DEFAULT: ```[]```

#### idBlacklist
A map from entity ID type to a list of IDs to reserve and skip for new entities of that type.

IDs are given as integers.

DEFAULT: ```[]``` (empty array)

EXAMPLE: ```[ 'item' => [ 1, 2, 3 ] ]```

#### string-limits
Limits to impose on various strings, such as multilanguage terms, various data types etc.

Supported string types:
 - **multilang** - multilanguage strings like labels, descriptions and such. (used to be the multilang-limits option)
 - **VT:monolingualtext**
 - **VT:string**
 - **PT:url**

Supported limits:
 - length - the maximum length of the string, in characters.

DEFAULT:
```php
[
	'multilang' => [
		'length' => 250,
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
```

#### multilang-limits
**DEPRECATED** ( use string-limits ).
Limits to impose on multilanguage strings like labels, descriptions and such. Supported limits:

#### urlSchemes
Which URL schemes should be allowed in URL data values.

Supported schemes are `ftps`, `ircs`, `mms`, `nntp`, `redis`, `sftp`, `telnet`, `worldwind` and `gopher`.
Schemes (protocols) added here will only have any effect if validation is supported for that protocol; that is, adding `ftps` will work, while adding `dummy` will do nothing.

DEFAULT: is ```['bzr', 'cvs', 'ftp', 'git', 'http', 'https', 'irc', 'mailto', 'ssh', 'svn']```

#### entityNamespaces {#entityNamespaces}
Defines which kind of entity is managed in which namespace.

It is given as an associative array mapping entity types such as `'item'` to namespaces (IDs or canonical names).
Mapping must be done for each type of entity that should be supported.
If entities are stored in alternative slots, the syntax <namespace>/<slot> can be used to define which slot to use.

EXAMPLE: ```['item' => 0, 'property' => 120, 'slottedEntity' => '123/slotname']```

#### foreignRepositories {#foreignRepositories}
An associative array mapping foreign repository names to settings relevant to the particular repository.
Each repository's settings are an associative array containing the following keys:

 - 'entityNamespaces' - A map of entity type identifiers (strings) that the local wiki supports from the foreign repository to namespaces (IDs or canonical names) related to pages of entities of the given type on foreign repository's wiki. If entities are stored in alternative slots, the syntax <namespace>/<slot> can be used to define which slot to use.
 - 'repoDatabase' - A symbolic database identifier (string) that MediaWiki's LBFactory class understands.
 - 'baseUri' - A base URI (string) for concept URIs. It should contain scheme and authority part of the URI.
 - 'prefixMapping' - A prefix mapping array, see also docs/foreign-entity-ids.wiki in the DataModel component.

#### unitStorage
Definition for unit conversion storage.

Should be in the format [ObjectFactory] understands.

EXAMPLE: ```[ 'class' => 'Wikibase\\Lib\\JsonUnitStorage', 'args' => [ 'myUnits.json' ] ]```

### canonicalLanguageCodes
Special non-canonical languages and their BCP 47 mappings

Based on: https://meta.wikimedia.org/wiki/Special_language_codes

#### dataBridgeEnabled {#repo_dataBridgeEnabled}
Enable the repo parts of the Data Bridge Feature; see the corresponding client setting for more information.

DEFAULT: ```false```

#### taintedReferencesEnabled {#repo_taintedReferencesEnabled}
Enable/Disable the tainted reference feature.

DEFAULT: ```false```

#### federatedPropertiesEnabled {#repo_federatedPropertiesEnabled}
Enable the federated properties feature. **Note that** once this feature is enable (set true), it must not be disabled (set false) again.
The behaviour is unpredicted if it is disabled after it was enabled.

DEFAULT: ```false```

### federatedPropertiesSourceScriptUrl {#repo_federatedPropertiesSourceScriptUrl}
A url path for the location of the source wikibase instance.
The set url path should allow access to both `index.php` and `api.php`

DEFAULT: ```https://www.wikidata.org/w/```

Client Settings
----------------------------------------------------------------------------------------

#### namespaces
List of namespaces on the client wiki that should have access to repository items.

DEFAULT: ```[]``` (Treated as setting is not set, ie. All namespaces are enabled.)

#### excludeNamespaces
List of namespaces on the client wiki to disable wikibase links, etc. for.

DEFAULT: ```[]```

EXAMPLE: `[ NS_USER_TALK ]`.

#### siteGlobalID {#client_siteGlobalID}
This site's global ID (e.g. `'itwiki'`), as used in the sites table.

DEFAULT: [$wgDBname].

#### siteLocalID
This site's local ID respective language code (e.g. `'it'`).

DEFAULT: [$wgLanguageCode].

*NOTE*: This setting will be removed once we can take this information from the sites table.

#### siteGroup
This site's site group (e.g. `'wikipedia'` or `'wikivoyage'`) as used in the sites table.

The setting is optional and falls back to site store lookup.
For performance reasons, it may be desirable to set this explicitly to avoid lookups.

### Repository

#### repoSiteId
Site ID of connected repository wiki

DEFAULT: is to assume both client and repo are the same.

DEFAULT: Same as [siteGlobalID] wikibase setting

#### repoSiteName
Site name of the connected repository wiki.

The default is to assume client and repo are same wiki, so defaults to global [$wgSitename] setting.
If not the same wiki, defaults to 'Wikidata'.
This setting can also be set to an i18n message key and will be handled as a message, if the message key exists so that the repo site name can be translatable.

DEFAULT: [$wgSitename]

#### repoNamespaces
An array telling the client wiki which namespaces on the repository are used for which entity type.

This is given as an associative array mapping entity type IDs such as Item::ENTITY_TYPE, to namespace names.
This information is used when constructing links to entities on the repository.

DEFAULT: (items in main namespace):
```
[
    'item' => "",
    'property' => 'Property'
]
```

Most Wikibases do not use the main namespace.
The example settings file does not use the main namespace.

### Urls, URIs & Paths

#### repoUrl
The repository's base URL, including the schema (protocol) and domain; This URL can be protocol-relative.

DEFAULT: ```//wikidata.org```

*NOTE*: This may be removed once we can get this information from the sites table.

#### repoScriptPath
The repository's script path.

DEFAULT: [$wgScriptPath] - Assuming that the repo's script path is the same as this wiki's script path.

*NOTE*: This may be removed once we can get this information from the sites table.

#### repoArticlePath
The repository's article path.

DEFAULT: [$wgArticlePath] - Assuming that the repo's article path is the same as this wiki's script path.

*NOTE*: This may be removed once we can get this information from the sites table.

#### propertyOrderUrl
URL to use for retrieving the property order used for sorting properties by property ID.

Will be ignored if set to null.

EXAMPLE: ```https://www.wikidata.org/w/index.php?title=MediaWiki:Wikibase-SortedProperties&action=raw&sp_ver=1```

### Transclusion & Data Access

#### allowDataTransclusion
Switch to enable data transclusion features like the ```{{#property}}``` parser function and the `wikibase` [Scribunto] module.

DEFAULT: ```true```

#### allowLocalShortDesc
Switch to enable local override of the central description with `{{SHORTDESC:}}`.

DEFAULT: ```false```

#### allowArbitraryDataAccess {#client_allowArbitraryDataAccess}
Switch to allow accessing arbitrary items from the `wikibase` [Scribunto] module and the via the parser functions (instead of just the item which is linked to the current page).

DEFAULT: ```true```

#### allowDataAccessInUserLanguage
Switch to allow accessing data in the user's language rather than the content language from the `wikibase` [Scribunto] module and the via the parser functions.

Useful for multilingual wikis
Allows users to split the ParserCache by user language.

DEFAULT: ```false```

#### disabledAccessEntityTypes
List of entity types that access to them in the client should be disabled.

DEFAULT: ```[]```

#### entityAccessLimit
Limit for the number of different full entities that can be loaded on any given page, via [Scribunto] or the property parser function.

DEFAULT: ```250```

#### referencedEntityIdAccessLimit
Maximum number of calls to `mw.wikibase.getReferencedEntityId` allowed on a single page.

#### referencedEntityIdMaxDepth
Maximum search depth for referenced entities in `mw.wikibase.getReferencedEntityId`.

#### referencedEntityIdMaxReferencedEntityVisits
Maximum number of entities to visit in a `mw.wikibase.getReferencedEntityId` call.

#### trackLuaFunctionCallsPerSiteGroup
Whether to track Lua function calls with a per-sitegroup key, like `MediaWiki.wikipedia.wikibase.client.scribunto.wikibase.functionName.call`.

#### trackLuaFunctionCallsPerWiki
Whether to track Lua function calls with a per-site key, like `MediaWiki.dewiki.wikibase.client.scribunto.wikibase.functionName.call`.

#### fineGrainedLuaTracking
Enable fine-grained tracking on entities accessed through Lua in client.

Not all (X) usage will be recorded, but each aspect will be recorded individually based on actual usage.

### Sitelinks

#### languageLinkSiteGroup
ID of the site group to be shown as language links.

DEFAULT: `null` (That is the site's own site group.)

#### badgeClassNames
A list of additional CSS class names for site links that have badges.

The array has to consist of serialized item IDs pointing to their CSS class names, like ```['Q101' => 'badge-goodarticle']```.
Note that this extension does not add any CSS to actually display the badges.

#### otherProjectsLinks
Site global ID list of sites which should be linked in the other project's sidebar section.

Empty value will suppress this section.

DEFAULT: Everything in the Wikibase [siteLinkGroups] setting.

### Recent Changes

#### injectRecentChanges {#client_injectRecentChanges}
Whether changes on the repository should be injected into this wiki's recent changes table, so they show up on watchlists, etc.

Requires the dispatchChanges.php script to run, and this wiki to be listed in the [localClientDatabases] setting on the repository.
See @ref md_docs_topics_change-propagation

#### showExternalRecentChanges
Whether changes on the repository should be displayed on Special:RecentChanges, Special:Watchlist, etc on the client wiki.

In contrast to [injectRecentChanges], this setting just removes the changes from the user interface.
This is intended to temporarily prevent external changes from showing in order to find or fix some issue on a live site.

DEFAULT: ```false```

#### recentChangesBatchSize {#client_recentChangesBatchSize}
Number of `recentchanges` table rows to create in each InjectRCRecordsJob, a job used to send client wikis notifications about relevant changes to entities.

Higher value mean fewer jobs but longer run-time per job.

DEFAULT: [wikiPageUpdaterDbBatchSize], for backwards compatibility, or MediaWiki core's [$wgUpdateRowsPerJob], which currently defaults to 300.

### Echo

#### sendEchoNotification
If true, allows users on the client wiki to get a notification when a page they created is connected to a repo item.

This requires the [Echo] extension.

#### echoIcon
If `sendEchoNotification` is set to `true`, you can also provide what icon the user will see.

The correct syntax is ```[ 'url' => '...' ]``` or ```[ 'path' => '...' ]``` where `path` is relative to [$wgExtensionAssetsPath].

DEFAULT: ```false``` (That is there will be the default Echo icon.)

### Data Bridge

#### dataBridgeEnabled {#client_dataBridgeEnabled}
Enables the Data Bridge Feature, which allows editing a repository directly from a client wiki.

To enable it, set this setting to `true` on both repo and client and also configure [dataBridgeHrefRegExp].

DEFAULT: ```false```

#### dataBridgeHrefRegExp {#client_dataBridgeHrefRegExp}
Regular expression to match edit links for which the Data Bridge is enabled.

Uses JavaScript syntax, with the first capturing group containing the title of the entity, the second one containing the entity ID (usually a part of the first capturing group) and the third one containing the property ID to edit.
Mandatory if [client dataBridgeEnabled] is set to `true` – there is no default value.

####dataBridgeEditTags
A list of tags for tracking edits through the Data Bridge.

Optional if [client dataBridgeEnabled] is set to `true`, with a default value of ```[]```.
Please note: you also have to create those tags in the target repository via Special:Tags.

#### dataBridgeIssueReportingLink
The URL for link to where the users can report errors with the Data Bridge.

It may have a `<body>` placeholder which will be replaced with some text containing more information about the error.

DEFAULT: `https://phabricator.wikimedia.org/maniphest/task/edit/form/1/?title=Wikidata+Bridge+error&description=${body}&tags=Wikidata-Bridge`

### Miscellaneous

#### repositories
An associative array mapping repository names to settings relevant to the particular repository.

Local repository is identified using the empty string as its name.
Each repository's settings are an associative array containing the following keys:

 - 'entityNamespaces': A map of entity type identifiers (strings) that the local wiki supports from the foreign repository to namespaces (IDs or canonical names) related to pages of entities of the given type on foreign repository's wiki. If entities are stored in alternative slots, the syntax <namespace>/<slot> can be used to define which slot to use.
 - 'repoDatabase': A symbolic database identifier (string) that MediaWiki's LBFactory class understands. Note that `false` would mean “this wiki's database”!
 - 'baseUri': A base URI (string) for concept URIs. It should contain scheme and authority part of the URI.
 - 'prefixMapping': A prefix mapping array, see also docs/foreign-entity-ids.wiki in the DataModel component.

#### propagateChangesToRepo
Switch to enable or disable the propagation of client changes to the repo.

DEFAULT: ```true```

#### entityUsagePerPageLimit
If a page in client uses too many aspects and entities, Wikibase issues a warning.

This setting determines value of that threshold.

DEFAULT: ```100```

#### pageSchemaNamespaces
An array of client namespace ids defaulting to empty (disabled)

Pages with a matching namespace will include a JSON-LD schema script for search engine optimization (SEO).

#### entitySchemaNamespace
Namespace id for entity schema data type

DEFAULT: ```640```

#### disabledUsageAspects
Array of usage aspects that should not be saved in the [wbc_entity_usage] table.

This supports aspect codes (like “T”, “L” or “X”), but not full aspect keys (like “L.de”).
For example ```[ 'D', 'C' ]``` can be used to disable description and statement usages.
A replacement usage type can be given in the form of ```[ 'usage-type-to-replace' => 'replacement' ]```.

#### wikiPageUpdaterDbBatchSize {#client_wikiPageUpdaterDbBatchSize}
DEPRECATED. If set, acts as a default for [purgeCacheBatchSize] and [recentChangesBatchSize].

#### purgeCacheBatchSize {#client_purgeCacheBatchSize}
Number of pages to process in each HTMLCacheUpdateJob, a job used to send client wikis notifications about relevant changes to entities.

A Higher value means fewer jobs but longer run-time per job.

DEFAULT: [wikiPageUpdaterDbBatchSize] (for backwards compatibility) or MediaWiki core's [$wgUpdateRowsPerJob] (which currently defaults to 300).

#### entityUsageModifierLimits
Associative array mapping usage type to the limit.

If number of modifiers for the given aspect of an entity passes this limit, it turns all modifiers to a general entity usage in the given aspect.
This is useful when with bad lua, a page in client uses all languages or statements in the repo causing the wbc_entity_usage become too big.

#### addEntityUsagesBatchSize
Batch size for adding entity usage records.

DEFAULT: ```500```

#### wellKnownReferencePropertyIds
Associative array mapping certain well-known property roles to the IDs of the properties fulfilling those roles.

When formatting references (currently, only for Data Bridge), a few properties are treated specially.
In this setting, those can be specified:
the keys `referenceUrl`, `title`, `statedIn`, `author`, `publisher`, `publicationDate` and `retrievedDate`
correspond to the Wikidata properties [reference URL], [title], [stated in], [author], [publisher], [publication date] and [retrieved] respectively.
Each property is optional.

DEFAULT: array mapping each well-known name to `null`.

[$wgDBname]: https://www.mediawiki.org/wiki/Manual:$wgDBname
[$wgMainCacheType]: https://www.mediawiki.org/wiki/Manual:$wgMainCacheType
[$wgMaxArticleSize]: https://www.mediawiki.org/wiki/Manual:$wgMaxArticleSize
[$wgRightsUrl]: https://www.mediawiki.org/wiki/Manual:$wgRightsUrl
[$wgRightsText]: https://www.mediawiki.org/wiki/Manual:$wgRightsText
[$wgLockManagers]: https://www.mediawiki.org/wiki/Manual:$wgLockManagers
[$wgDBDefaultGroup]: https://www.mediawiki.org/wiki/Manual:$wgDBDefaultGroup
[$wgLanguageCode]: https://www.mediawiki.org/wiki/Manual:$wgLanguageCode
[$wgSitename]: https://www.mediawiki.org/wiki/Manual:$wgSitename
[$wgServer]: https://www.mediawiki.org/wiki/Manual:$wgServer
[$wgUpdateRowsPerJob]: https://www.mediawiki.org/wiki/Manual:$wgUpdateRowsPerJob
[$wgCdnMaxAge]: https://www.mediawiki.org/wiki/Manual:$wgCdnMaxAge
[$wgExtensionAssetsPath]: https://www.mediawiki.org/wiki/Manual:$wgExtensionAssetsPath
[$wgScriptPath]: https://www.mediawiki.org/wiki/Manual:$wgScriptPath
[$wgArticlePath]: https://www.mediawiki.org/wiki/Manual:$wgArticlePath
[GeoData]: https://www.mediawiki.org/wiki/Extension:GeoData
[Echo]: https://www.mediawiki.org/wiki/Extension:Echo
[ObjectFactory]: https://www.mediawiki.org/wiki/ObjectFactory
[page property]: https://www.mediawiki.org/wiki/Manual:Page_props_table
[Scribunto]: (https://www.mediawiki.org/wiki/Scribunto)
[siteLinkGroups]: #common_siteLinkGroups
[entitySources]: #common_entitySources
[useTermsTableSearchFields]: #common_useTermsTableSearchFields
[sharedCacheKeyPrefix]: #common_sharedCacheKeyPrefix
[termboxEnabled]: #repo_termboxEnabled
[client dataBridgeEnabled]: #client_dataBridgeEnabled
[dataBridgeHrefRegExp]: #client_dataBridgeHrefRegExp
[injectRecentChanges]: #client_injectRecentChanges
[localClientDatabases]: #client_localClientDatabases
[recentChangesBatchSize]: #client_recentChangesBatchSize
[purgeCacheBatchSize]: #client_purgeCacheBatchSize
[wikiPageUpdaterDbBatchSize]: #client_wikiPageUpdaterDbBatchSize
[siteGlobalID]: #client_siteGlobalID
[wb_terms]: @ref md_docs_sql_wb_terms
[wbc_entity_usage]: @ref md_docs_sql_wbc_entity_usage
[reference URL]: https://www.wikidata.org/wiki/Property:P854
[title]: https://www.wikidata.org/wiki/Property:P1476
[stated in]: https://www.wikidata.org/wiki/Property:P248
[author]: https://www.wikidata.org/wiki/Property:P50
[publisher]: https://www.wikidata.org/wiki/Property:P123
[publication date]: https://www.wikidata.org/wiki/Property:P577
[retrieved]: https://www.wikidata.org/wiki/Property:P813
