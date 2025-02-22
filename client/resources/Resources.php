<?php

use MediaWiki\MediaWikiServices;
use Wikibase\Client\DataBridge\DataBridgeConfigValueProvider;
use Wikibase\Client\WikibaseClient;
use Wikibase\Lib\Modules\MediaWikiConfigModule;

return call_user_func( function() {
	$moduleTemplate = [
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'Wikibase/client/resources',
	];

	return [

		'wikibase.client.init' => $moduleTemplate + [
			'skinStyles' => [
				'modern' => 'wikibase.client.css',
				'monobook' => 'wikibase.client.css',
				'timeless' => 'wikibase.client.css',
				'vector' => [
					'wikibase.client.css',
					'wikibase.client.vector.css'
				]
			],
		],

		'wikibase.client.data-bridge.init' => [
			'factory' => function () {
				$clientSettings = WikibaseClient::getDefaultInstance()->getSettings();
				return new ResourceLoaderFileModule(
					[
						'scripts' => [
							'data-bridge.init.js'
						],
						'targets' => $clientSettings->getSetting( 'dataBridgeEnabled' ) ?
							[ 'desktop', 'mobile' ] :
							[],
						'dependencies' => [
							'oojs-ui-windows',
							'mw.config.values.wbDataBridgeConfig',
						],
						'remoteExtPath' => 'Wikibase/client/data-bridge/dist',
					],
					__DIR__ . '/../data-bridge/dist'
				);
			},
		],

		'wikibase.client.data-bridge.externalModifiers' => [
			'factory' => function () {
				$clientSettings = WikibaseClient::getDefaultInstance()->getSettings();
				return new ResourceLoaderFileModule(
					[
						'styles' => [
							'edit-links.css',
							'box-layout.css',
						],
						'targets' => $clientSettings->getSetting( 'dataBridgeEnabled' ) ?
							[ 'desktop', 'mobile' ] :
							[],
						'remoteExtPath' => 'Wikibase/client/data-bridge/modules/externalModifiers',
					],
					__DIR__ . '/../data-bridge/modules/externalModifiers'
				);
			},
		],

		'mw.config.values.wbDataBridgeConfig' => [
			'factory' => function () {
				$clientSettings = WikibaseClient::getDefaultInstance()->getSettings();
				return new MediaWikiConfigModule(
					[
						'getconfigvalueprovider' => function() use ( $clientSettings ) {
							return new DataBridgeConfigValueProvider(
								$clientSettings,
								MediaWikiServices::getInstance()->getMainConfig()->get( 'EditSubmitButtonLabelPublish' )
							);
						},
						'targets' => $clientSettings->getSetting( 'dataBridgeEnabled' ) ?
							[ 'desktop', 'mobile' ] :
							[],
					]
				);
			}
		],

		'wikibase.client.data-bridge.app' => [
			'factory' => function () {
				$clientSettings = WikibaseClient::getDefaultInstance()->getSettings();
				return new ResourceLoaderFileModule(
					[
						'scripts' => [
							'data-bridge.common.js'
						],
						'styles' => [
							'data-bridge.css',
						],
						'targets' => $clientSettings->getSetting( 'dataBridgeEnabled' ) ?
							[ 'desktop', 'mobile' ] :
							[],
						'remoteExtPath' => 'Wikibase/client/data-bridge/dist',
						'dependencies' => [
							'vue',
							'vuex',
							'mediawiki.jqueryMsg',
						],
						'messages' => [
							'wikibase-client-data-bridge-dialog-title',
							'wikibase-client-data-bridge-permissions-error',
							'wikibase-client-data-bridge-permissions-error-info',
							'wikibase-client-data-bridge-protected-on-repo-head',
							'wikibase-client-data-bridge-protected-on-repo-body',
							'wikibase-client-data-bridge-semiprotected-on-repo-head',
							'wikibase-client-data-bridge-semiprotected-on-repo-body',
							'wikibase-client-data-bridge-cascadeprotected-on-repo-head',
							'wikibase-client-data-bridge-cascadeprotected-on-repo-body',
							'wikibase-client-data-bridge-blocked-on-repo-head',
							'wikibase-client-data-bridge-blocked-on-repo-body',
							'wikibase-client-data-bridge-cascadeprotected-on-client-head',
							'wikibase-client-data-bridge-cascadeprotected-on-client-body',
							'wikibase-client-data-bridge-blocked-on-client-head',
							'wikibase-client-data-bridge-blocked-on-client-body',
							'wikibase-client-data-bridge-permissions-error-unknown-head',
							'wikibase-client-data-bridge-permissions-error-unknown-body',
							'wikibase-client-data-bridge-edit-decision-heading',
							'wikibase-client-data-bridge-edit-decision-replace-label',
							'wikibase-client-data-bridge-edit-decision-replace-description',
							'wikibase-client-data-bridge-edit-decision-update-label',
							'wikibase-client-data-bridge-edit-decision-update-description',
							'wikibase-client-data-bridge-references-heading',
							'wikibase-client-data-bridge-anonymous-edit-warning-heading',
							'wikibase-client-data-bridge-anonymous-edit-warning-message',
							'wikibase-client-data-bridge-anonymous-edit-warning-proceed',
							'wikibase-client-data-bridge-anonymous-edit-warning-login',
							'wikibase-client-data-bridge-license-heading',
							'wikibase-client-data-bridge-license-body',
							'wikibase-client-data-bridge-bailout-heading',
							'wikibase-client-data-bridge-bailout-suggestion-go-to-repo',
							'wikibase-client-data-bridge-bailout-suggestion-go-to-repo-button',
							'wikibase-client-data-bridge-bailout-suggestion-edit-article',
							'wikibase-client-data-bridge-unsupported-datatype-error-head',
							'wikibase-client-data-bridge-unsupported-datatype-error-body',
							'wikibase-client-data-bridge-deprecated-statement-error-head',
							'wikibase-client-data-bridge-deprecated-statement-error-body',
							'wikibase-client-data-bridge-ambiguous-statement-error-head',
							'wikibase-client-data-bridge-ambiguous-statement-error-body',
							'wikibase-client-data-bridge-somevalue-error-head',
							'wikibase-client-data-bridge-somevalue-error-body',
							'wikibase-client-data-bridge-saving-error-heading',
							'wikibase-client-data-bridge-saving-error-message',
							'wikibase-client-data-bridge-novalue-error-head',
							'wikibase-client-data-bridge-novalue-error-body',
							'wikibase-client-data-bridge-unknown-error-heading',
							'wikibase-client-data-bridge-unknown-error-message',
							'wikibase-client-data-bridge-error-report',
							'wikibase-client-data-bridge-error-reload-bridge',
							'wikibase-client-data-bridge-error-go-back',
							'wikibase-client-data-bridge-error-retry-save',
							'wikibase-client-data-bridge-reference-note',
							'wikibase-client-data-bridge-thank-you-head',
							'wikibase-client-data-bridge-thank-you-edit-reference-on-repo-body',
							'wikibase-client-data-bridge-thank-you-edit-reference-on-repo-button',
							'savechanges',
							'publishchanges',
							'cancel',
							'grouppage-sysop',
							'emailuser',
						],
					],
					__DIR__ . '/../data-bridge/dist'
				);
			},
		],

		'wikibase.client.miscStyles' => $moduleTemplate + [
			'styles' => [
				'wikibase.client.page-move.css',
				'wikibase.client.changeslist.css',
			]
		],

		'wikibase.client.linkitem.init' => $moduleTemplate + [
			"packageFiles" => [
				"wikibase.client.linkitem.init.js",
				[
					"name" => "config.json",
					"callback" => "Wikibase\\ClientHooks::getSiteConfiguration"
				]
			],
			'messages' => [
				'unknown-error'
			],
			'dependencies' => [
				'jquery.spinner'
			],
		],

		'jquery.wikibase.linkitem' => $moduleTemplate + [
			'scripts' => [
				'wikibase.client.getMwApiForRepo.js',
				'jquery.wikibase/jquery.wikibase.linkitem.js',
				'wikibase.client.PageConnector.js'
			],
			'styles' => [
				'jquery.wikibase/jquery.wikibase.linkitem.css'
			],
			'dependencies' => [
				'jquery.spinner',
				'jquery.ui',
				'jquery.ui.suggester',
				'jquery.wikibase.siteselector',
				'jquery.wikibase.wbtooltip',
				'mediawiki.api',
				'mediawiki.util',
				'mediawiki.jqueryMsg',
				'jquery.event.special.eachchange',
				'wikibase.sites',
				'wikibase.api.RepoApi',
			],
			'messages' => [
				'wikibase-error-unexpected',
				'wikibase-linkitem-alreadylinked',
				'wikibase-linkitem-title',
				'wikibase-linkitem-linkpage',
				'wikibase-linkitem-selectlink',
				'wikibase-linkitem-input-site',
				'wikibase-linkitem-input-page',
				'wikibase-linkitem-confirmitem-text',
				'wikibase-linkitem-confirmitem-button',
				'wikibase-linkitem-success-link',
				'wikibase-linkitem-close',
				'wikibase-linkitem-not-loggedin-title',
				'wikibase-linkitem-not-loggedin',
				'wikibase-linkitem-failure',
				'wikibase-linkitem-failed-modify',
				'wikibase-replicationnote',
				'wikibase-sitelinks-sitename-columnheading',
				'wikibase-sitelinks-link-columnheading'
			],
		],

		'wikibase.client.action.edit.collapsibleFooter' => $moduleTemplate + [
			'scripts' => 'wikibase.client.action.edit.collapsibleFooter.js',
			'dependencies' => [
				'jquery.makeCollapsible',
				'mediawiki.storage',
				'mediawiki.icon',
			],
		]
	];
} );
