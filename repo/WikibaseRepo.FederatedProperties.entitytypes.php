<?php

/**
 * Definition of entity service overrides for Federated Properties.
 * The array returned by the code below is supposed to be merged with the content of
 * repo/WikibaseRepo.entitytypes.php.
 *
 * @note: This is bootstrap code, it is executed for EVERY request. Avoid instantiating
 * objects or loading classes here!
 *
 * @see docs/entiytypes.md
 *
 * @license GPL-2.0-or-later
 */

use Wikibase\Lib\EntityTypeDefinitions as Def;
use Wikibase\Lib\Store\EntityArticleIdNullLookup;
use Wikibase\Repo\WikibaseRepo;

return [
	'property' => [
		Def::ARTICLE_ID_LOOKUP_CALLBACK => function () {
			return new EntityArticleIdNullLookup();
		},
		Def::URL_LOOKUP_CALLBACK => function () {
			return WikibaseRepo::getDefaultInstance()->newFederatedPropertiesServiceFactory()->newApiEntityUrlLookup();
		},
		Def::TITLE_TEXT_LOOKUP_CALLBACK => function () {
			return WikibaseRepo::getDefaultInstance()->newFederatedPropertiesServiceFactory()->newApiEntityTitleTextLookup();
		},
		Def::ENTITY_SEARCH_CALLBACK => function() {
			return WikibaseRepo::getDefaultInstance()->newFederatedPropertiesServiceFactory()->newApiEntitySearchHelper();
		},
		Def::ENTITY_ID_HTML_LINK_FORMATTER_CALLBACK => function( Language $language ) {
			return WikibaseRepo::getDefaultInstance()->newFederatedPropertiesServiceFactory()->newEntityIdHtmlLinkFormatter( $language );
		},
		Def::PREFETCHING_TERM_LOOKUP_CALLBACK => function() {
			return WikibaseRepo::getDefaultInstance()->newFederatedPropertiesServiceFactory()->newApiPrefetchingTermLookup();
		},
	]
];
