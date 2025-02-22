<?php

namespace Wikibase\Client\Tests\Unit\Hooks;

use IJobSpecification;
use JobQueue;
use JobQueueGroup;
use Psr\Log\NullLogger;
use ReflectionMethod;
use Title;
use User;
use Wikibase\Client\Hooks\UpdateRepoHookHandlers;
use Wikibase\Client\NamespaceChecker;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\SiteLinkLookup;

/**
 * @covers \Wikibase\Client\Hooks\UpdateRepoHookHandlers
 *
 * @group WikibaseClient
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class UpdateRepoHookHandlersTest extends \PHPUnit\Framework\TestCase {

	public function doArticleDeleteCompleteProvider() {
		return [
			'Success' => [
				true, true, new ItemId( 'Q42' )
			],
			'propagateChangesToRepo set to false' => [
				false, false, new ItemId( 'Q42' )
			],
			'Not connected to an item' => [
				false, true, null
			],
		];
	}

	/**
	 * @dataProvider doArticleDeleteCompleteProvider
	 */
	public function testDoArticleDeleteComplete(
		$expectsSuccess,
		$propagateChangesToRepo,
		ItemId $itemId = null
	) {
		$handler = $this->newUpdateRepoHookHandlers(
			true,
			$expectsSuccess,
			$propagateChangesToRepo,
			'UpdateRepoOnDelete',
			$itemId
		);
		$title = $this->getTitle();

		$this->assertTrue(
			$handler->doArticleDeleteComplete( $title, $this->createMock( User::class ) )
		);

		$this->assertSame(
			$expectsSuccess,
			isset( $title->wikibasePushedDeleteToRepo ) && $title->wikibasePushedDeleteToRepo,
			'Delete got propagated to repo.'
		);
	}

	public function doTitleMoveCompleteProvider() {
		return [
			'Success' => [
				true, true, true, new ItemId( 'Q42' )
			],
			'Page is moved into a non-Wikibase NS' => [
				false, false, true, new ItemId( 'Q42' )
			],
			'propagateChangesToRepo set to false' => [
				false, true, false, new ItemId( 'Q42' )
			],
			'Not connected to an item' => [
				false, true, false, null
			],
		];
	}

	/**
	 * @dataProvider doTitleMoveCompleteProvider
	 */
	public function testDoTitleMoveComplete(
		$expectsSuccess,
		$isWikibaseEnabled,
		$propagateChangesToRepo,
		ItemId $itemId = null
	) {
		$handler = $this->newUpdateRepoHookHandlers(
			$isWikibaseEnabled,
			$expectsSuccess,
			$propagateChangesToRepo,
			'UpdateRepoOnMove',
			$itemId
		);
		$oldTitle = $this->getTitle();
		$newTitle = $this->getTitle();

		$this->assertTrue(
			$handler->doTitleMoveComplete( $oldTitle, $newTitle, $this->createMock( User::class ) )
		);

		$this->assertSame(
			$expectsSuccess,
			isset( $newTitle->wikibasePushedMoveToRepo ) && $newTitle->wikibasePushedMoveToRepo,
			'Move got propagated to repo.'
		);

		$this->assertFalse( property_exists( $oldTitle, 'wikibasePushedMoveToRepo' ),
			'Should not touch $oldTitle' );
	}

	public function testNewFromGlobalState() {
		$reflectionMethod = new ReflectionMethod( UpdateRepoHookHandlers::class, 'newFromGlobalState' );
		$reflectionMethod->setAccessible( true );
		$handler = $reflectionMethod->invoke( null );

		$this->assertInstanceOf( UpdateRepoHookHandlers::class, $handler );
	}

	/**
	 * @return Title
	 */
	private function getTitle() {
		// get a Title mock with all methods mocked except the magics __get and __set to
		// allow the DeprecationHelper trait methods to work and handle non-existing class variables
		// correctly, see UpdateRepoHookHandlers.php:doArticleDeleteComplete
		$title = $this->createPartialMock(
			Title::class,
			array_diff( get_class_methods( Title::class ), [ '__get', '__set' ] )
		);
		$title->expects( $this->any() )
			->method( 'getPrefixedText' )
			->will( $this->returnValue( 'UpdateRepoHookHandlersTest' ) );

		return $title;
	}

	private function newUpdateRepoHookHandlers(
		$isWikibaseEnabled,
		$expectsJobToBePushed,
		$propagateChangesToRepo,
		$jobName,
		ItemId $itemId = null
	) {
		$namespaceChecker = $this->getMockBuilder( NamespaceChecker::class )
			->disableOriginalConstructor()
			->getMock();
		$namespaceChecker->expects( $this->any() )
			->method( 'isWikibaseEnabled' )
			->will( $this->returnValue( $isWikibaseEnabled ) );

		$jobQueue = $this->getMockBuilder( JobQueue::class )
			->disableOriginalConstructor()
			->setMethods( [ 'supportsDelayedJobs' ] )
			->getMockForAbstractClass();
		$jobQueue->expects( $this->any() )
			->method( 'supportsDelayedJobs' )
			->will( $this->returnValue( true ) );

		$jobQueueGroup = $this->getMockBuilder( JobQueueGroup::class )
			->disableOriginalConstructor()
			->getMock();
		$jobQueueGroup->expects( $expectsJobToBePushed ? $this->once() : $this->never() )
			->method( 'push' )
			->with( $this->isInstanceOf( IJobSpecification::class ) );
		$jobQueueGroup->expects( $expectsJobToBePushed ? $this->once() : $this->never() )
			->method( 'get' )
			->with( $jobName )
			->will( $this->returnValue( $jobQueue ) );

		$siteLinkLookup = $this->createMock( SiteLinkLookup::class );
		$siteLinkLookup->expects( $this->any() )
			->method( 'getItemIdForLink' )
			->with( 'clientwiki', 'UpdateRepoHookHandlersTest' )
			->will( $this->returnValue( $itemId ) );

		return new UpdateRepoHookHandlers(
			$namespaceChecker,
			$jobQueueGroup,
			$siteLinkLookup,
			new NullLogger(),
			'repowiki',
			'clientwiki',
			$propagateChangesToRepo
		);
	}

}
