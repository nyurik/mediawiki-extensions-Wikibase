<?php

namespace Wikibase\Repo\Tests\Api;

use ApiBase;
use ApiResult;
use HashSiteStore;
use Language;
use MediaWiki\MediaWikiServices;
use Serializers\Serializer;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\SerializerFactory;
use Wikibase\DataModel\Services\Lookup\InMemoryDataTypeLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\Api\ApiErrorReporter;
use Wikibase\Repo\Api\ApiHelperFactory;
use Wikibase\Repo\Api\EntityLoadingHelper;
use Wikibase\Repo\Api\EntitySavingHelper;
use Wikibase\Repo\Api\ResultBuilder;
use Wikibase\Repo\EditEntity\MediawikiEditEntityFactory;
use Wikibase\Repo\Localizer\ExceptionLocalizer;
use Wikibase\SummaryFormatter;

/**
 * @covers \Wikibase\Repo\Api\ApiHelperFactory
 *
 * @group Wikibase
 * @group WikibaseAPI
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class ApiHelperFactoryTest extends \PHPUnit\Framework\TestCase {

	private function newApiHelperFactory() {
		$summaryFormatter = $this->getMockBuilder( SummaryFormatter::class )
			->disableOriginalConstructor()
			->getMock();

		$editEntityFactory = $this->getMockBuilder( MediawikiEditEntityFactory::class )
			->disableOriginalConstructor()
			->getMock();

		$serializerFactory = $this->getMockBuilder( SerializerFactory::class )
			->disableOriginalConstructor()
			->getMock();

		return new ApiHelperFactory(
			$this->createMock( EntityTitleLookup::class ),
			$this->createMock( ExceptionLocalizer::class ),
			new InMemoryDataTypeLookup(),
			new HashSiteStore(),
			$summaryFormatter,
			$this->createMock( EntityRevisionLookup::class ),
			$editEntityFactory,
			$serializerFactory,
			$this->createMock( Serializer::class ),
			new ItemIdParser(),
			MediaWikiServices::getInstance()->getPermissionManager()
		);
	}

	/**
	 * @return ApiBase
	 */
	private function newApiModule() {
		$language = Language::factory( 'en' );

		$result = $this->getMockBuilder( ApiResult::class )
			->disableOriginalConstructor()
			->getMock();

		$api = $this->getMockBuilder( ApiBase::class )
			->disableOriginalConstructor()
			->getMock();

		$api->expects( $this->any() )
			->method( 'getResult' )
			->will( $this->returnValue( $result ) );

		$api->expects( $this->any() )
			->method( 'getLanguage' )
			->will( $this->returnValue( $language ) );

		return $api;
	}

	public function testGetResultBuilder() {
		$api = $this->newApiModule();
		$factory = $this->newApiHelperFactory();

		$resultBuilder = $factory->getResultBuilder( $api );
		$this->assertInstanceOf( ResultBuilder::class, $resultBuilder );
	}

	public function testGetErrorReporter() {
		$api = $this->newApiModule();
		$factory = $this->newApiHelperFactory();

		$errorReporter = $factory->getErrorReporter( $api );
		$this->assertInstanceOf( ApiErrorReporter::class, $errorReporter );
	}

	public function testGetEntitySavingHelper() {
		$factory = $this->newApiHelperFactory();

		$helper = $factory->getEntitySavingHelper( $this->newApiModule() );
		$this->assertInstanceOf( EntitySavingHelper::class, $helper );
	}

	public function testGetEntityLoadingHelper() {
		$factory = $this->newApiHelperFactory();

		$helper = $factory->getEntityLoadingHelper( $this->newApiModule() );
		$this->assertInstanceOf( EntityLoadingHelper::class, $helper );
	}

}
