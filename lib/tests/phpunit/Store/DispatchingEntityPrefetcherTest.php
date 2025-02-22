<?php

namespace Wikibase\Lib\Tests\Store;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Entity\EntityPrefetcher;
use Wikibase\Lib\Store\DispatchingEntityPrefetcher;

/**
 * @covers \Wikibase\Lib\Store\DispatchingEntityPrefetcher
 *
 * @group Wikibase
 * @group WikibaseStore
 *
 * @license GPL-2.0-or-later
 */
class DispatchingEntityPrefetcherTest extends \PHPUnit\Framework\TestCase {

	public function testPrefetchGroupsIdsByRepositoryAndDelegatesPrefetchingToRepositorySpecificPrefetcher() {
		$localIdOne = new ItemId( 'Q100' );
		$localIdTwo = new ItemId( 'Q101' );
		$foreignIdOne = new ItemId( 'foo:Q200' );

		$localPrefetcher = $this->createMock( EntityPrefetcher::class );
		$localPrefetcher->expects( $this->atLeastOnce() )
			->method( 'prefetch' )
			->with( [ $localIdOne, $localIdTwo ] );

		$fooPrefetcher = $this->createMock( EntityPrefetcher::class );
		$fooPrefetcher->expects( $this->atLeastOnce() )
			->method( 'prefetch' )
			->with( [ $foreignIdOne ] );

		$dispatchingPrefetcher = new DispatchingEntityPrefetcher( [
			'' => $localPrefetcher,
			'foo' => $fooPrefetcher,
		] );

		$dispatchingPrefetcher->prefetch( [ $localIdOne, $foreignIdOne, $localIdTwo ] );
	}

	public function testPrefetchIgnoresIdsFromUnknownRepositories() {
		$localId = new ItemId( 'Q100' );
		$foreignId = new ItemId( 'foo:Q200' );

		$localPrefetcher = $this->createMock( EntityPrefetcher::class );
		$localPrefetcher->expects( $this->atLeastOnce() )
			->method( 'prefetch' )
			->with( [ $localId ] );

		$dispatchingPrefetcher = new DispatchingEntityPrefetcher( [
			'' => $localPrefetcher,
		] );

		$dispatchingPrefetcher->prefetch( [ $localId, $foreignId ] );
	}

	public function testGivenEntityIdFromKnownRepo_purgeCallsPurgeOnPrefetcherForThatRepository() {
		$foreignId = new ItemId( 'foo:Q200' );

		$localPrefetcher = $this->createMock( EntityPrefetcher::class );
		$localPrefetcher->expects( $this->never() )->method( 'purge' );

		$fooPrefetcher = $this->createMock( EntityPrefetcher::class );
		$fooPrefetcher->expects( $this->atLeastOnce() )
			->method( 'purge' )
			->with( $foreignId );

		$dispatchingPrefetcher = new DispatchingEntityPrefetcher( [
			'' => $localPrefetcher,
			'foo' => $fooPrefetcher,
		] );

		$dispatchingPrefetcher->purge( $foreignId );
	}

	public function testGivenEntityIdFromUnknownRepo_purgeDoesNotDelegateCall() {
		$foreignId = new ItemId( 'foo:Q200' );

		$localPrefetcher = $this->createMock( EntityPrefetcher::class );
		$localPrefetcher->expects( $this->never() )->method( 'purge' );

		$dispatchingPrefetcher = new DispatchingEntityPrefetcher( [
			'' => $localPrefetcher,
		] );

		$dispatchingPrefetcher->purge( $foreignId );
	}

	public function testPurgeAllRequestsAllPrefetchersToPurgeTheirCaches() {
		$localPrefetcher = $this->createMock( EntityPrefetcher::class );
		$localPrefetcher->expects( $this->atLeastOnce() )->method( 'purgeAll' );

		$fooPrefetcher = $this->createMock( EntityPrefetcher::class );
		$fooPrefetcher->expects( $this->atLeastOnce() )->method( 'purgeAll' );

		$dispatchingPrefetcher = new DispatchingEntityPrefetcher( [
			'' => $localPrefetcher,
			'foo' => $fooPrefetcher,
		] );

		$dispatchingPrefetcher->purgeAll();
	}

	public function provideInvalidConstructorArguments() {
		return [
			'empty prefetcher list' => [ [] ],
			'not a string as a key' => [ [ 0 => $this->createMock( EntityPrefetcher::class ) ] ],
			'not a repository name as a key' => [ [ 'fo:o' => $this->createMock( EntityPrefetcher::class ) ] ],
			'not an EntityPrefetcher' => [ [ '' => new ItemId( 'Q100' ) ] ],
		];
	}

	/**
	 * @dataProvider provideInvalidConstructorArguments
	 */
	public function testGivenInvalidArgumentsConstructorThrowsException( $args ) {
		$this->expectException( InvalidArgumentException::class );

		new DispatchingEntityPrefetcher( $args );
	}

}
