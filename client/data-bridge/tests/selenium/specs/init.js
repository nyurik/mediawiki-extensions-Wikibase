const assert = require( 'assert' ),
	Api = require( 'wdio-mediawiki/Api' ),
	Util = require( 'wdio-mediawiki/Util' ),
	DataBridgePage = require( '../pageobjects/dataBridge.page' ),
	WikibaseApi = require( 'wdio-wikibase/wikibase.api' );

describe( 'init', () => {
	beforeEach( () => {
		browser.deleteCookies();
	} );

	it( 'opens app in OOUI dialog', () => {
		const title = DataBridgePage.getDummyTitle();
		const propertyId = browser.call( () => WikibaseApi.getProperty( 'string' ) );
		const entityId = browser.call( () => WikibaseApi.createItem( 'data bridge browser test item', {
			'claims': [ {
				'mainsnak': {
					'snaktype': 'value',
					'property': propertyId,
					'datavalue': { 'value': 'ExampleString', 'type': 'string' },
				},
				'type': 'statement',
				'rank': 'normal',
			} ],
		} ) );
		const content = DataBridgePage.createInfoboxWikitext( [ {
			label: 'official website',
			entityId,
			propertyId,
			editFlow: 'single-best-value',
		} ] );
		browser.call( () => Api.bot().then( ( bot ) => bot.edit( title, content ) ) );

		DataBridgePage.openBridgeOnPage( title );

		assert.ok( DataBridgePage.app.isDisplayed() );
	} );

	it( 'indicates loading while app gathers its data', () => {
		const title = DataBridgePage.getDummyTitle();
		const propertyId = browser.call( () => WikibaseApi.getProperty( 'string' ) );
		const entityId = browser.call( () => WikibaseApi.createItem( 'data bridge browser test item', {
			'claims': [ {
				'mainsnak': {
					'snaktype': 'value',
					'property': propertyId,
					'datavalue': { 'value': 'ExampleString', 'type': 'string' },
				},
				'type': 'statement',
				'rank': 'normal',
			} ],
		} ) );
		const content = DataBridgePage.createInfoboxWikitext( [ {
			label: 'official website',
			entityId,
			propertyId,
			editFlow: 'single-best-value',
		} ] );
		browser.call( () => Api.bot().then( ( bot ) => bot.edit( title, content ) ) );

		DataBridgePage.open( title );

		browser.setNetworkConditions( { latency: 100, throughput: 1000 } );

		DataBridgePage.overloadedLink.click();
		browser.waitUntil(
			() => {
				return DataBridgePage.loadingBar.isDisplayed();
			},
			{
				interval: 500,
				timeout: 10000,
			}
		);
		DataBridgePage.loadingBar.waitForExist( undefined, true );
		assert.ok( DataBridgePage.bridge.isDisplayed() );
	} );

	describe( 'once app is launched', () => {
		it( 'shows the current targetValue', () => {
			const title = DataBridgePage.getDummyTitle();
			const propertyId = browser.call( () => WikibaseApi.getProperty( 'string' ) );
			const stringPropertyExampleValue = 'ExampleString';
			const entityId = browser.call( () => WikibaseApi.createItem( 'data bridge browser test item', {
				'claims': [ {
					'mainsnak': {
						'snaktype': 'value',
						'property': propertyId,
						'datavalue': { 'value': stringPropertyExampleValue, 'type': 'string' },
					},
					'type': 'statement',
					'rank': 'normal',
				} ],
			} ) );
			const content = DataBridgePage.createInfoboxWikitext( [ {
				label: 'official website',
				entityId,
				propertyId,
				editFlow: 'single-best-value',
			} ] );
			browser.call( () => Api.bot().then( ( bot ) => bot.edit( title, content ) ) );

			DataBridgePage.openBridgeOnPage( title );

			DataBridgePage.bridge.waitForDisplayed( 5000 );
			assert.ok( DataBridgePage.bridge.isDisplayed() );
			assert.strictEqual( DataBridgePage.value.getValue(), stringPropertyExampleValue );
		} );

		it( 'shows the current targetReference', () => {
			const title = DataBridgePage.getDummyTitle();
			const stringPropertyId = browser.call( () => WikibaseApi.getProperty( 'string' ) );
			const urlPropertyId = browser.call( () => WikibaseApi.getProperty( 'url' ) );
			const entityId = browser.call( () => WikibaseApi.createItem( 'data bridge browser test item', {
				'claims': { [ stringPropertyId ]: [ {
					'mainsnak': {
						'snaktype': 'value',
						'property': stringPropertyId,
						'datavalue': { 'value': 'a string', 'type': 'string' },
					},
					'type': 'statement',
					'rank': 'normal',
					'references': [
						{
							'snaks': {
								[ urlPropertyId ]: [ {
									'snaktype': 'value',
									'property': urlPropertyId,
									'datatype': 'string',
									'datavalue': { 'type': 'string', 'value': 'https://example.com' },
								} ],
								[ stringPropertyId ]: [
									{
										'snaktype': 'value',
										'property': stringPropertyId,
										'datatype': 'string',
										'datavalue': { 'type': 'string', 'value': 'A' },
									},
									{
										'snaktype': 'value',
										'property': stringPropertyId,
										'datatype': 'string',
										'datavalue': { 'type': 'string', 'value': 'B' },
									},
								],
							},
							'snaks-order': [ stringPropertyId, urlPropertyId ],
						},
						{
							'snaks': { [ stringPropertyId ]: [ {
								'snaktype': 'value',
								'property': stringPropertyId,
								'datatype': 'string',
								'datavalue': { 'type': 'string', 'value': 'C' },
							} ] },
							'snaks-order': [ stringPropertyId ],
						},
					],
				} ] },
			} ) );
			const content = DataBridgePage.createInfoboxWikitext( [ {
				label: 'official website',
				entityId,
				propertyId: stringPropertyId,
				editFlow: 'single-best-value',
			} ] );
			browser.call( () => Api.bot().then( ( bot ) => bot.edit( title, content ) ) );

			DataBridgePage.open( title );
			DataBridgePage.overloadedLink.click();
			DataBridgePage.bridge.waitForDisplayed();

			assert.ok( DataBridgePage.bridge.isDisplayed() );
			assert.strictEqual( DataBridgePage.nthReference( 1 ).getText(), 'A. B. https://example.com.' );
			assert.strictEqual( DataBridgePage.nthReference( 2 ).getText(), 'C.' );
		} );

		describe( 'target property label', () => {

			/**
			 * @param {Object} propertyData entity data for the property, usually contains labels
			 * @param {String} pageContentLanguage override for content language of the page
			 * @param {String|null} expectedLabel null means property ID
			 */
			function test( propertyData, pageContentLanguage, expectedLabel ) {
				const title = DataBridgePage.getDummyTitle();
				const propertyId = browser.call( () => WikibaseApi.createProperty(
					'string',
					propertyData
				) );
				if ( expectedLabel === null ) {
					expectedLabel = propertyId;
				}
				const stringPropertyExampleValue = 'ExampleString';
				const entityId = browser.call( () => WikibaseApi.createItem( 'data bridge browser test item', {
					'claims': [ {
						'mainsnak': {
							'snaktype': 'value',
							'property': propertyId,
							'datavalue': { 'value': stringPropertyExampleValue, 'type': 'string' },
						},
						'type': 'statement',
						'rank': 'normal',
					} ],
				} ) );
				const content = DataBridgePage.createInfoboxWikitext( [ {
					label: 'official website',
					entityId,
					propertyId,
					editFlow: 'single-best-value',
				} ] );
				browser.call( () => Api.bot().then( ( bot ) => bot.edit( title, content ) ) );

				DataBridgePage.open( title );
				browser.execute(
					( pageContentLanguage ) => window.mw.config.set( 'wgPageContentLanguage', pageContentLanguage ),
					pageContentLanguage
				);
				DataBridgePage.overloadedLink.click();
				DataBridgePage.bridge.waitForDisplayed( 10000 );

				browser.waitUntil(
					() => DataBridgePage.propertyLabel.getText() === expectedLabel,
					5000,
					`${DataBridgePage.propertyLabel.getText()} is not equal to ${expectedLabel}`
				);
			}

			it( 'uses the label from the page content language', () => {
				const language = 'de',
					label = Util.getTestString( 'Zieleigenschaft-' );
				test( { labels: { [ language ]: { value: label, language } } }, language, label );
			} );

			it( 'uses the label from a non-English fallback language', () => {
				const language = 'de',
					label = Util.getTestString( 'Zieleigenschaft-' );
				test( { labels: { [ language ]: { value: label, language } } }, 'de-formal', label );
			} );

			it( 'uses the label from the English fallback language', () => {
				const language = 'en',
					label = Util.getTestString( 'target-property-' );
				test( { labels: { [ language ]: { value: label, language } } }, 'de', label );
			} );

			it( 'uses the property ID if no label in fallback chain found', () => {
				const language = 'de',
					label = Util.getTestString( 'Zieleigenschaft-' );
				test( { labels: { [ language ]: { value: label, language } } }, 'he', null );
			} );
		} );
	} );
} );
