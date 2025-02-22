<?php

namespace Wikibase\Lib;

use ExtensionRegistry;
use ResourceLoader;

/**
 * File defining the hook handlers for the WikibaseLib extension.
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Werner < daniel.a.r.werner@gmail.com >
 * @author H. Snater < mediawiki@snater.com >
 */
final class LibHooks {

	/**
	 * Callback called after extension registration,
	 * for any work that cannot be done directly in extension.json.
	 */
	public static function onRegistration() {
		global $wgResourceModules;

		$wgResourceModules = array_merge(
			$wgResourceModules,
			require __DIR__ . '/../resources/Resources.php'
		);
	}

	/**
	 * Hook to add PHPUnit test cases.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @param string[] &$paths
	 *
	 * @return bool
	 */
	public static function registerPhpUnitTests( array &$paths ) {
		$paths[] = __DIR__ . '/../tests/phpunit/';
		$paths[] = __DIR__ . '/../../data-access/tests/phpunit/';

		return true;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderTestModules
	 *
	 * @param array &$testModules
	 * @param ResourceLoader $resourceLoader
	 *
	 * @return boolean
	 */
	public static function registerQUnitTests( array &$testModules, ResourceLoader $resourceLoader ) {
		$testModules['qunit'] = array_merge(
			$testModules['qunit'],
			require __DIR__ . '/../tests/qunit/resources.php'
		);

		return true;
	}

	/**
	 * Register ResourceLoader modules with dynamic dependencies.
	 *
	 * @param ResourceLoader $resourceLoader
	 *
	 * @return bool
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ) {
		$moduleTemplate = [
			'localBasePath' => __DIR__ . '/../',
			'remoteExtPath' => 'Wikibase/lib',
		];

		$modules = [
			'wikibase.Site' => $moduleTemplate + [
				'scripts' => [
					'resources/wikibase.Site.js',
				],
				'dependencies' => [
					'mediawiki.util',
					'util.inherit',
					'wikibase',
				],
			],
		];

		$isUlsLoaded = ExtensionRegistry::getInstance()->isLoaded( 'UniversalLanguageSelector' );
		if ( $isUlsLoaded ) {
			$modules['wikibase.Site']['dependencies'][] = 'ext.uls.mediawiki';
		}

		$resourceLoader->register( $modules );

		return true;
	}

	/**
	 * Called when generating the extensions credits, use this to change the tables headers.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ExtensionTypes
	 *
	 * @param array &$extensionTypes
	 *
	 * @return boolean
	 */
	public static function onExtensionTypes( array &$extensionTypes ) {
		// @codeCoverageIgnoreStart
		$extensionTypes['wikibase'] = wfMessage( 'version-wikibase' )->text();

		return true;
		// @codeCoverageIgnoreEnd
	}

}
