<?php

namespace Wikibase\Lib\Tests\Store;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\DispatchingPropertyInfoLookup;
use Wikibase\Lib\Store\PropertyInfoLookup;

/**
 * @covers \Wikibase\Lib\Store\DispatchingPropertyInfoLookup
 *
 * @group WikibaseStore
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class DispatchingPropertyInfoLookupTest extends \PHPUnit\Framework\TestCase {

	private $localPropertyInfo;
	private $fooPropertyInfo;

	public function setUp() : void {
		$this->localPropertyInfo = [
			'P23' => [ PropertyInfoLookup::KEY_DATA_TYPE => 'string' ],
			'P42' => [ PropertyInfoLookup::KEY_DATA_TYPE => 'commonsMedia', 'foo' => 'bar' ]
		];
		$this->fooPropertyInfo = [
			'foo:P123' => [ PropertyInfoLookup::KEY_DATA_TYPE => 'string', 'foo' => 'bar' ],
			'foo:P42' => [ PropertyInfoLookup::KEY_DATA_TYPE => 'commonsMedia' ]
		];
	}

	public function testGivenUnknownRepository_getPropertyInfoReturnsNull() {
		$lookup = new DispatchingPropertyInfoLookup( [
			'' => new MockPropertyInfoLookup( $this->localPropertyInfo ),
			'foo' => new MockPropertyInfoLookup( $this->fooPropertyInfo ),
		] );

		$this->assertNull( $lookup->getPropertyInfo( new PropertyId( 'bar:P123' ) ) );
	}

	public function testGivenDifferentPropertyIds_getPropertyInfoDispatchesAccordingToRepository() {
		$lookup = new DispatchingPropertyInfoLookup( [
			'' => new MockPropertyInfoLookup( $this->localPropertyInfo ),
			'foo' => new MockPropertyInfoLookup( $this->fooPropertyInfo ),
		] );

		$this->assertSame(
			$this->localPropertyInfo['P23'],
			$lookup->getPropertyInfo( new PropertyId( 'P23' ) )
		);
		$this->assertSame(
			$this->fooPropertyInfo['foo:P42'],
			$lookup->getPropertyInfo( new PropertyId( 'foo:P42' ) )
		);
	}

	public function testGivenMultiplePropertyInfoLookups_getAllPropertyInfoCombinesResults() {
		$lookup = new DispatchingPropertyInfoLookup( [
			'' => new MockPropertyInfoLookup( $this->localPropertyInfo ),
			'foo' => new MockPropertyInfoLookup( $this->fooPropertyInfo ),
		] );

		$this->assertSame(
			array_merge( $this->localPropertyInfo, $this->fooPropertyInfo ),
			$lookup->getAllPropertyInfo()
		);
	}

	public function testGivenMultiplePropertyInfoLookups_getPropertyInfoForDataTypeCombinesResults() {
		$lookup = new DispatchingPropertyInfoLookup( [
			'' => new MockPropertyInfoLookup( $this->localPropertyInfo ),
			'foo' => new MockPropertyInfoLookup( $this->fooPropertyInfo ),
		] );

		$this->assertSame(
			[ 'P23' => $this->localPropertyInfo['P23'], 'foo:P123' => $this->fooPropertyInfo['foo:P123'] ],
			$lookup->getPropertyInfoForDataType( 'string' )
		);
		$this->assertSame(
			[ 'P42' => $this->localPropertyInfo['P42'], 'foo:P42' => $this->fooPropertyInfo['foo:P42'] ],
			$lookup->getPropertyInfoForDataType( 'commonsMedia' )
		);
	}

	/**
	 * @dataProvider provideInvalidForeignLookups
	 */
	public function testGivenInvalidPropertyInfoLookup_exceptionIsThrown( $lookups ) {
		$this->expectException( InvalidArgumentException::class );
		new DispatchingPropertyInfoLookup( $lookups );
	}

	public function provideInvalidForeignLookups() {
		return [
			'no lookups given' => [ [] ],
			'not an implementation of PropertyInfoLookup given as a lookup' => [
				[ '' => new PropertyId( 'P123' ) ],
			],
			'non-string keys' => [
				[
					'' => $this->createMock( PropertyInfoLookup::class ),
					100 => $this->createMock( PropertyInfoLookup::class ),
				],
			],
			'repo name containing colon' => [
				[
					'' => $this->createMock( PropertyInfoLookup::class ),
					'fo:oo' => $this->createMock( PropertyInfoLookup::class ),
				],
			],
		];
	}

}
