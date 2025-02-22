<?php

namespace Wikibase\Repo\Tests\Interactors;

use FauxRequest;
use PHPUnit\Framework\MockObject\Matcher\InvokedRecorder;
use RequestContext;
use Status;
use Title;
use User;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\RevisionedUnresolvedRedirectException;
use Wikibase\Lib\Tests\MockRepository;
use Wikibase\Repo\EditEntity\EditFilterHookRunner;
use Wikibase\Repo\Interactors\ItemRedirectCreationInteractor;
use Wikibase\Repo\Interactors\RedirectCreationException;
use Wikibase\Repo\Store\EntityPermissionChecker;
use Wikibase\Repo\Store\EntityTitleStoreLookup;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\Interactors\ItemRedirectCreationInteractor
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class RedirectCreationInteractorTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @var MockRepository|null
	 */
	private $mockRepository = null;

	protected function setUp() : void {
		parent::setUp();

		$this->mockRepository = new MockRepository();

		// empty item
		$item = new Item( new ItemId( 'Q11' ) );
		$this->mockRepository->putEntity( $item );

		// non-empty item
		$item->setLabel( 'en', 'Foo' );
		$item->setId( new ItemId( 'Q12' ) );
		$this->mockRepository->putEntity( $item );

		// a property
		$prop = Property::newFromType( 'string' );
		$prop->setId( new PropertyId( 'P11' ) );
		$this->mockRepository->putEntity( $prop );

		// another property
		$prop->setId( new PropertyId( 'P12' ) );
		$this->mockRepository->putEntity( $prop );

		// redirect
		$redirect = new EntityRedirect( new ItemId( 'Q22' ), new ItemId( 'Q12' ) );
		$this->mockRepository->putRedirect( $redirect );
	}

	/**
	 * @return EntityPermissionChecker
	 */
	private function getPermissionChecker() {
		$permissionChecker = $this->createMock( EntityPermissionChecker::class );

		$permissionChecker->expects( $this->any() )
			->method( 'getPermissionStatusForEntityId' )
			->will( $this->returnCallback( function( User $user ) {
				$userWithoutPermissionName = 'UserWithoutPermission';

				if ( $user->getName() === $userWithoutPermissionName ) {
					return Status::newFatal( 'permissiondenied' );
				} else {
					return Status::newGood();
				}
			} ) );

		return $permissionChecker;
	}

	/**
	 * @param InvokedRecorder|null $invokeCount
	 * @param Status|null $hookReturn
	 *
	 * @return EditFilterHookRunner
	 */
	public function getMockEditFilterHookRunner(
		$invokeCount = null,
		Status $hookReturn = null
	) {
		if ( $invokeCount === null ) {
			$invokeCount = $this->any();
		}
		if ( $hookReturn === null ) {
			$hookReturn = Status::newGood();
		}
		$mock = $this->getMockBuilder( EditFilterHookRunner::class )
			->setMethods( [ 'run' ] )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $invokeCount )
			->method( 'run' )
			->will( $this->returnValue( $hookReturn ) );
		return $mock;
	}

	/**
	 * @param InvokedRecorder|null $efHookCalls
	 * @param Status|null $efHookStatus
	 * @param User|null $user
	 *
	 * @return ItemRedirectCreationInteractor
	 */
	private function newInteractor(
		$efHookCalls = null,
		Status $efHookStatus = null,
		User $user = null
	) {
		if ( !$user ) {
			$user = $this->createMock( User::class );
		}

		$summaryFormatter = WikibaseRepo::getDefaultInstance()->getSummaryFormatter();

		$context = new RequestContext();
		$context->setRequest( new FauxRequest() );

		$interactor = new ItemRedirectCreationInteractor(
			$this->mockRepository,
			$this->mockRepository,
			$this->getPermissionChecker(),
			$summaryFormatter,
			$user,
			$this->getMockEditFilterHookRunner( $efHookCalls, $efHookStatus ),
			$this->mockRepository,
			$this->getMockEntityTitleLookup()
		);

		return $interactor;
	}

	/**
	 * @return EntityTitleStoreLookup
	 */
	private function getMockEntityTitleLookup() {
		$titleLookup = $this->createMock( EntityTitleStoreLookup::class );

		$titleLookup->expects( $this->any() )
			->method( 'getTitleForId' )
			->will( $this->returnCallback( function( EntityId $id ) {
				$title = $this->createMock( Title::class );
				$title->expects( $this->any() )
					->method( 'isDeleted' )
					->will( $this->returnValue( $id->getSerialization() === 'Q666' ) );
				return $title;
			} ) );

		return $titleLookup;
	}

	public function createRedirectProvider_success() {
		return [
			'redirect empty entity' => [ new ItemId( 'Q11' ), new ItemId( 'Q12' ) ],
			'update redirect' => [ new ItemId( 'Q22' ), new ItemId( 'Q11' ) ],
			'over deleted item' => [ new ItemId( 'Q666' ), new ItemId( 'Q11' ) ],
		];
	}

	/**
	 * @dataProvider createRedirectProvider_success
	 */
	public function testCreateRedirect_success( EntityId $fromId, EntityId $toId ) {
		$interactor = $this->newInteractor( $this->once() );

		$interactor->createRedirect( $fromId, $toId, false );

		try {
			$this->mockRepository->getEntity( $fromId );
			$this->fail( 'getEntity( ' . $fromId->getSerialization() . ' ) did not throw an UnresolvedRedirectException' );
		} catch ( RevisionedUnresolvedRedirectException $ex ) {
			$this->assertEquals( $toId->getSerialization(), $ex->getRedirectTargetId()->getSerialization() );
		}
	}

	public function createRedirectProvider_failure() {
		return [
			'source not found' => [
				new ItemId( 'Q77' ),
				new ItemId( 'Q12' ),
				'no-such-entity',
				[ 'Q77' ]
			],
			'target not found' => [
				new ItemId( 'Q11' ),
				new ItemId( 'Q77' ),
				'no-such-entity',
				[ 'Q77' ]
			],
			'target is a redirect' => [
				new ItemId( 'Q11' ),
				new ItemId( 'Q22' ),
				'target-is-redirect',
				[ 'Q22' ]
			],
			'target is incompatible' => [
				new ItemId( 'Q11' ),
				new PropertyId( 'P11' ),
				'target-is-incompatible',
				[]
			],
			'target and source are the same' => [
				new ItemId( 'Q11' ),
				new ItemId( 'Q11' ),
				'source-and-target-are-the-same',
				[]
			],

			'source not empty' => [
				new ItemId( 'Q12' ),
				new ItemId( 'Q11' ),
				'origin-not-empty',
				[ 'Q12' ]
			],
			'can\'t redirect' => [
				new PropertyId( 'P11' ),
				new PropertyId( 'P12' ),
				'cant-redirect',
				[]
			],
			'can\'t redirect EditFilter' => [
				new ItemId( 'Q11' ),
				new ItemId( 'Q12' ),
				'cant-redirect-due-to-edit-filter-hook',
				[],
				Status::newFatal( 'EF' )
			],
		];
	}

	/**
	 * @dataProvider createRedirectProvider_failure
	 */
	public function testCreateRedirect_failure(
		EntityId $fromId,
		EntityId $toId,
		$expectedCode,
		array $messageParams,
		Status $efStatus = null
	) {
		$interactor = $this->newInteractor( null, $efStatus );

		try {
			$interactor->createRedirect( $fromId, $toId, false );
			$this->fail( 'createRedirect not fail with error ' . $expectedCode . ' as expected!' );
		} catch ( RedirectCreationException $ex ) {
			$this->assertEquals( $expectedCode, $ex->getErrorCode() );
			$this->assertSame( 'wikibase-redirect-' . $expectedCode, $ex->getKey() );
			$this->assertSame( $messageParams, $ex->getParams() );
		}
	}

	public function testSetRedirect_noPermission() {
		$this->expectException( RedirectCreationException::class );

		$user = User::newFromName( 'UserWithoutPermission' );

		$interactor = $this->newInteractor( null, null, $user );
		$interactor->createRedirect( new ItemId( 'Q11' ), new ItemId( 'Q12' ), false );
	}

}
