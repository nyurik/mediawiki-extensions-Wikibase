<?php

namespace Wikibase\View\Tests;

use HashSiteStore;
use InvalidArgumentException;
use Language;
use ValueFormatters\BasicNumberLocalizer;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\DataModel\Services\Statement\Grouper\NullStatementGrouper;
use Wikibase\Lib\DataTypeFactory;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\Lib\LanguageFallbackChain;
use Wikibase\Lib\LanguageNameLookup;
use Wikibase\Lib\Store\EntityInfo;
use Wikibase\Lib\Store\PropertyOrderProvider;
use Wikibase\View\CacheableEntityTermsView;
use Wikibase\View\EditSectionGenerator;
use Wikibase\View\EntityIdFormatterFactory;
use Wikibase\View\HtmlSnakFormatterFactory;
use Wikibase\View\ItemView;
use Wikibase\View\LanguageDirectionalityLookup;
use Wikibase\View\LocalizedTextProvider;
use Wikibase\View\PropertyView;
use Wikibase\View\SpecialPageLinker;
use Wikibase\View\StatementSectionsView;
use Wikibase\View\Template\TemplateFactory;
use Wikibase\View\Template\TemplateRegistry;
use Wikibase\View\ViewFactory;

/**
 * @covers \Wikibase\View\ViewFactory
 *
 * @uses Wikibase\View\StatementHtmlGenerator
 * @uses Wikibase\View\EditSectionGenerator
 * @uses Wikibase\View\EntityTermsView
 * @uses Wikibase\View\EntityView
 * @uses Wikibase\View\ItemView
 * @uses Wikibase\View\PropertyView
 * @uses Wikibase\View\SiteLinksView
 * @uses Wikibase\View\SnakHtmlGenerator
 * @uses Wikibase\View\StatementGroupListView
 * @uses Wikibase\View\StatementSectionsView
 * @uses Wikibase\View\Template\Template
 * @uses Wikibase\View\Template\TemplateFactory
 * @uses Wikibase\View\Template\TemplateRegistry
 *
 * @group Wikibase
 * @group WikibaseView
 *
 * @license GPL-2.0-or-later
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Thiemo Kreuz
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class ViewFactoryTest extends \PHPUnit\Framework\TestCase {

	private function newViewFactory(
		EntityIdFormatterFactory $htmlFactory = null,
		EntityIdFormatterFactory $plainFactory = null
	) {
		$templateFactory = new TemplateFactory( new TemplateRegistry( [] ) );

		$languageNameLookup = $this->createMock( LanguageNameLookup::class );
		$languageNameLookup->expects( $this->never() )
			->method( 'getName' );

		return new ViewFactory(
			$htmlFactory ?: $this->getEntityIdFormatterFactory( SnakFormatter::FORMAT_HTML ),
			$plainFactory ?: $this->getEntityIdFormatterFactory( SnakFormatter::FORMAT_PLAIN ),
			$this->getSnakFormatterFactory(),
			new NullStatementGrouper(),
			$this->createMock( PropertyOrderProvider::class ),
			new HashSiteStore(),
			new DataTypeFactory( [] ),
			$templateFactory,
			$languageNameLookup,
			$this->createMock( LanguageDirectionalityLookup::class ),
			new BasicNumberLocalizer(),
			[],
			[],
			[],
			$this->createMock( LocalizedTextProvider::class ),
			$this->createMock( SpecialPageLinker::class )
		);
	}

	/**
	 * @dataProvider invalidConstructorArgumentsProvider
	 */
	public function testConstructorThrowsException(
		EntityIdFormatterFactory $htmlFormatterFactory,
		EntityIdFormatterFactory $plainFormatterFactory
	) {
		$this->expectException( InvalidArgumentException::class );
		$this->newViewFactory( $htmlFormatterFactory, $plainFormatterFactory );
	}

	public function invalidConstructorArgumentsProvider() {
		$htmlFactory = $this->getEntityIdFormatterFactory( SnakFormatter::FORMAT_HTML );
		$plainFactory = $this->getEntityIdFormatterFactory( SnakFormatter::FORMAT_PLAIN );
		$wikiFactory = $this->getEntityIdFormatterFactory( SnakFormatter::FORMAT_WIKI );

		return [
			[ $wikiFactory, $plainFactory ],
			[ $htmlFactory, $wikiFactory ],
		];
	}

	public function testNewItemView() {
		$factory = $this->newViewFactory();
		$itemView = $factory->newItemView(
			Language::factory( 'en' ),
			new LanguageFallbackChain( [] ),
			new EntityInfo( [] ),
			$this->createMock( CacheableEntityTermsView::class )
		);

		$this->assertInstanceOf( ItemView::class, $itemView );
	}

	public function testNewPropertyView() {
		$factory = $this->newViewFactory();
		$propertyView = $factory->newPropertyView(
			Language::factory( 'en' ),
			new LanguageFallbackChain( [] ),
			new EntityInfo( [] ),
			$this->createMock( CacheableEntityTermsView::class )
		);

		$this->assertInstanceOf( PropertyView::class, $propertyView );
	}

	public function testNewStatementSectionsView() {
		$statementSectionsView = $this->newViewFactory()->newStatementSectionsView(
			'de',
			$this->createMock( LabelDescriptionLookup::class ),
			new LanguageFallbackChain( [] ),
			$this->createMock( EditSectionGenerator::class )
		);

		$this->assertInstanceOf( StatementSectionsView::class, $statementSectionsView );
	}

	/**
	 * @param string $format
	 *
	 * @return EntityIdFormatterFactory
	 */
	private function getEntityIdFormatterFactory( $format ) {
		$entityIdFormatter = $this->createMock( EntityIdFormatter::class );

		$formatterFactory = $this->createMock( EntityIdFormatterFactory::class );

		$formatterFactory->method( 'getOutputFormat' )
			->will( $this->returnValue( $format ) );

		$formatterFactory->method( 'getEntityIdFormatter' )
			->will( $this->returnValue( $entityIdFormatter ) );

		return $formatterFactory;
	}

	/**
	 * @return HtmlSnakFormatterFactory
	 */
	private function getSnakFormatterFactory() {
		$snakFormatter = $this->createMock( SnakFormatter::class );

		$snakFormatter->method( 'getFormat' )
			->will( $this->returnValue( SnakFormatter::FORMAT_HTML ) );

		$snakFormatterFactory = $this->createMock( HtmlSnakFormatterFactory::class );

		$snakFormatterFactory->method( 'getSnakFormatter' )
			->will( $this->returnValue( $snakFormatter ) );

		return $snakFormatterFactory;
	}

}
