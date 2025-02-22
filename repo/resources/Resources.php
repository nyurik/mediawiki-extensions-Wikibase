<?php

use Wikibase\Lib\Modules\DataTypesModule;
use Wikibase\Lib\Modules\MediaWikiConfigModule;
use Wikibase\Repo\WikibaseRepo;

/**
 * Wikibase Repo ResourceLoader modules
 *
 * @license GPL-2.0-or-later
 * @author Daniel Werner
 * @author H. Snater < mediawiki@snater.com >
 *
 * @codeCoverageIgnoreStart
 */
return call_user_func( function() {
	$moduleTemplate = [
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Wikibase/repo/resources',
	];

	$formattersPaths = [
		'localBasePath' => __DIR__ . '/formatters',
		'remoteExtPath' => 'Wikibase/repo/resources/formatters',
	];

	$expertsPaths = [
		'localBasePath' => __DIR__ . '/experts',
		'remoteExtPath' => 'Wikibase/repo/resources/experts',
	];

	$modules = [
		'wikibase.formatters.ApiValueFormatter' => $formattersPaths + [
			'scripts' => [
				'ApiValueFormatter.js',
			],
			'dependencies' => [
				'wikibase',
				'util.inherit',
				'valueFormatters',
			],
		],

		'wikibase.experts.__namespace' => $expertsPaths + [
			'scripts' => [
				'__namespace.js'
			],
			'dependencies' => [
				'wikibase',
			]
		],

		'wikibase.experts.Entity' => $expertsPaths + [
			'scripts' => [
				'Entity.js',
			],
			'dependencies' => [
				'jquery.event.special.eachchange',
				'jquery.valueview.Expert',
				'jquery.valueview.experts.StringValue',
				'jquery.wikibase.entityselector',
				'mw.config.values.wbRepo',
				'util.inherit',
				'wikibase.experts.__namespace',
			],
		],

		'wikibase.experts.Item' => $expertsPaths + [
			'scripts' => [
				'Item.js',
			],
			'dependencies' => [
				'jquery.valueview.Expert',
				'wikibase.experts.__namespace',
				'wikibase.experts.Entity',
			],
		],

		'wikibase.experts.Property' => $expertsPaths + [
			'scripts' => [
				'Property.js',
			],
			'dependencies' => [
				'jquery.valueview.Expert',
				'wikibase.experts.__namespace',
				'wikibase.experts.Entity',
			],
		],

		'mw.config.values.wbDataTypes' => $moduleTemplate + [
			'class' => DataTypesModule::class,
			'datatypefactory' => function() {
				return WikibaseRepo::getDefaultInstance()->getDataTypeFactory();
			},
			'datatypesconfigvarname' => 'wbDataTypes',
		],

		// Temporary, see: T199197
		'mw.config.values.wbRefTabsEnabled' => $moduleTemplate + [
			'class' => MediaWikiConfigModule::class,
			'getconfigvalueprovider' => function () {
				return WikibaseRepo::getDefaultInstance()->getSettingsValueProvider(
					'wbRefTabsEnabled',
					'enableRefTabs'
				);
			},
		],

		'wikibase.entityPage.entityLoaded' => $moduleTemplate + [
			'scripts' => [
				'wikibase.entityPage.entityLoaded.js',
			],
			'targets' => [ 'desktop', 'mobile' ],
			'dependencies' => [
				'wikibase',
				'mediawiki.Uri',
			],
		],

		'wikibase.EntityInitializer' => $moduleTemplate + [
			'packageFiles' => [
				'wikibase.EntityInitializer.js',

				[
				"name" => "config.json",
				"callback" => function () {
					return [
						'entityTypes' => WikibaseRepo::getDefaultInstance()->getEntityTypesConfigValue()
					];
				}
			],

			],
			'dependencies' => [
				'wikibase',
				'wikibase.serialization'
			]
		],

		'wikibase.getUserLanguages' => $moduleTemplate + [
			'packageFiles' => [
				'wikibase.getUserLanguages.js',
				[
					'name' => 'termLanguages.json',
					'callback' => function () {
						return WikibaseRepo::getDefaultInstance()->getTermsLanguages()->getLanguages();
					},
				]
			],
			'dependencies' => [
				'wikibase',
			],
			'targets' => [ 'desktop', 'mobile' ],
		],

		'wikibase.ui.entityViewInit' => [
			'packageFiles' => [
				'repo/resources/wikibase.ui.entityViewInit.js',

				'repo/resources/experts/getStore.js',
				'repo/resources/dataTypes/wikibase.dataTypeStore.js',
				'repo/resources/dataTypes/DataTypeStore.js',
				'repo/resources/dataTypes/DataType.js',
				'repo/resources/parsers/getStore.js',
				'repo/resources/parsers/getApiBasedValueParserConstructor.js',
				'repo/resources/formatters/ApiValueFormatterFactory.js',
				'view/resources/wikibase/view/ViewFactoryFactory.js',
				'view/resources/wikibase/wikibase.RevisionStore.js',
				'view/resources/wikibase/view/StructureEditorFactory.js',
				'view/resources/wikibase/store/store.EntityStore.js',
				'view/resources/wikibase/store/store.ApiEntityStore.js',
				'view/resources/wikibase/store/store.CachingEntityStore.js',
				'view/resources/wikibase/store/store.CombiningEntityStore.js',
				'view/resources/wikibase/entityIdFormatter/EntityIdHtmlFormatter.js',
				'view/resources/wikibase/entityIdFormatter/EntityIdPlainFormatter.js',
				'view/resources/wikibase/entityIdFormatter/CachingEntityIdHtmlFormatter.js',
				'view/resources/wikibase/entityIdFormatter/CachingEntityIdPlainFormatter.js',
				'view/resources/wikibase/entityIdFormatter/DataValueBasedEntityIdHtmlFormatter.js',
				'view/resources/wikibase/entityIdFormatter/DataValueBasedEntityIdPlainFormatter.js',
				'view/resources/jquery/wikibase/toolbar/jquery.wikibase.toolbaritem.js',
				'view/resources/jquery/wikibase/toolbar/jquery.wikibase.toolbarbutton.js',
				'view/resources/jquery/wikibase/toolbar/jquery.wikibase.toolbar.js',
				'view/resources/jquery/wikibase/toolbar/jquery.wikibase.singlebuttontoolbar.js',
				'view/resources/jquery/wikibase/toolbar/jquery.wikibase.addtoolbar.js',
				'view/resources/jquery/wikibase/toolbar/jquery.wikibase.edittoolbar.js',
				'view/resources/jquery/wikibase/toolbar/jquery.wikibase.removetoolbar.js',
				'view/resources/wikibase/view/ToolbarFactory.js',
				'view/resources/wikibase/wikibase.ValueFormatterFactory.js',
				'repo/resources/wikibase.PropertyDataTypeStore.js',
				[
					"name" => "repo/resources/config.json",
					"callback" => function () {
						$settings = WikibaseRepo::getDefaultInstance()->getSettings();
						return [
							'geoShapeStorageApiEndpoint' => $settings->getSetting( 'geoShapeStorageApiEndpointUrl' )
						];
					}
				],
			],
			'styles' => [
				'view/resources/jquery/wikibase/toolbar/themes/default/jquery.wikibase.toolbaritem.css',
				'view/resources/jquery/wikibase/toolbar/themes/default/jquery.wikibase.edittoolbar.css',
			],
			'dependencies' => [
				'mediawiki.api',
				'mediawiki.cookie',
				'mediawiki.page.watch.ajax',
				'mediawiki.Uri',
				'mediawiki.user',
				'mw.config.values.wbRepo',
				'mw.config.values.wbDataTypes',
				'jquery.wikibase.wbtooltip',
				'wikibase',
				'wikibase.api.ValueCaller',
				'wikibase.formatters.ApiValueFormatter',
				'wikibase.datamodel',
				'wikibase.entityPage.entityLoaded',
				'wikibase.entityChangers.EntityChangersFactory',
				'wikibase.EntityInitializer',
				'wikibase.api.RepoApi',
				'wikibase.sites',
				'wikibase.WikibaseContentLanguages',
				'wikibase.getUserLanguages',
				'wikibase.experts.__namespace',
				'wikibase.experts.modules',
				'wikibase.view.__namespace',
				'wikibase.view.ReadModeViewFactory',
				'wikibase.view.ControllerViewFactory',
				'jquery.valueview.experts.StringValue',
				'jquery.valueview.experts.UnDeserializableValue',
				'jquery.valueview.ExpertStore',
				'dataValues.values',
				'valueParsers.parsers',
				'valueParsers.ValueParserStore',
				'jquery.wikibase.toolbar.styles',
			],
			'messages' => [
				'pagetitle',
				'wikibase-copyrighttooltip-acknowledge',
				'wikibase-anonymouseditwarning',
				'wikibase-entity-item',
				'wikibase-entity-property',
				'wikibase-add',
				'wikibase-cancel',
				'wikibase-edit',
				'wikibase-remove',
				'wikibase-remove-inprogress',
				'wikibase-save',
				'wikibase-publish',
			],
			'localBasePath' => dirname( dirname( __DIR__ ) ),
			'remoteExtPath' => 'Wikibase',
		],

		'wikibase.ui.entitysearch' => $moduleTemplate + [
			'packageFiles' => [
				'wikibase.ui.entitysearch.js',

				'jquery.wikibase/jquery.wikibase.entitysearch.js',
			],
			'styles' => [
				'jquery.wikibase/themes/default/jquery.wikibase.entitysearch.css',
			],
			'dependencies' => [
				'jquery.event.special.eachchange',
				'jquery.spinner',
				'jquery.wikibase.entityselector',
			],
			'messages' => [
				'searchsuggest-containing',
			]
		],

		/* Wikibase special pages */

		'wikibase.special.newEntity' => $moduleTemplate + [
			'scripts' => [
				'wikibase.special/wikibase.special.newEntity.js',
			]
		],

		'wikibase.special.mergeItems' => $moduleTemplate + [
			'scripts' => [
				'wikibase.special/wikibase.special.mergeItems.js',
			]
		],

		'wikibase.experts.modules' => $moduleTemplate + [
				'factory' => function () {
					return WikibaseRepo::getDefaultInstance()->getPropertyValueExpertsModule();
				}
		],
	];

	return $modules;
} );
