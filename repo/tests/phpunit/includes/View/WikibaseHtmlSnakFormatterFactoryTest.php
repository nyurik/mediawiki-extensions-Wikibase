<?php

namespace Wikibase\Repo\Tests\View;

use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\Lib\Formatters\FormatterLabelDescriptionLookupFactory;
use Wikibase\Lib\Formatters\OutputFormatSnakFormatterFactory;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\Lib\LanguageFallbackChain;
use Wikibase\Repo\View\WikibaseHtmlSnakFormatterFactory;

/**
 * @covers \Wikibase\Repo\View\WikibaseHtmlSnakFormatterFactory
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
class WikibaseHtmlSnakFormatterFactoryTest extends \PHPUnit\Framework\TestCase {

	public function testGetSnakFormatter() {
		$snakFormatter = $this->createMock( SnakFormatter::class );
		$languageFallbackChain = new LanguageFallbackChain( [] );
		$labelDescriptionLookup = $this->createMock( LabelDescriptionLookup::class );

		$outputFormatSnakFormatterFactory = $this->getMockBuilder(
				OutputFormatSnakFormatterFactory::class
			)
			->disableOriginalConstructor()
			->getMock();

		$outputFormatSnakFormatterFactory->expects( $this->once() )
			->method( 'getSnakFormatter' )
			->with(
				SnakFormatter::FORMAT_HTML_VERBOSE,
				new FormatterOptions( [
					ValueFormatter::OPT_LANG => 'en',
					FormatterLabelDescriptionLookupFactory::OPT_LANGUAGE_FALLBACK_CHAIN => $languageFallbackChain,
					FormatterLabelDescriptionLookupFactory::OPT_LABEL_DESCRIPTION_LOOKUP => $labelDescriptionLookup
				] )
			)
			->will( $this->returnValue( $snakFormatter ) );

		$factory = new WikibaseHtmlSnakFormatterFactory( $outputFormatSnakFormatterFactory );

		$snakFormatterReturned = $factory->getSnakFormatter(
			'en',
			$languageFallbackChain,
			$labelDescriptionLookup
		);
		$this->assertEquals( $snakFormatter, $snakFormatterReturned );
	}

}
