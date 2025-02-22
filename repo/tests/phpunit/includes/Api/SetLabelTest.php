<?php

namespace Wikibase\Repo\Tests\Api;

use ApiUsageException;
use MediaWiki\MediaWikiServices;
use User;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\Api\SetLabel
 * @covers \Wikibase\Repo\Api\ModifyTerm
 * @covers \Wikibase\Repo\Api\ModifyEntity
 *
 * @group Database
 * @group medium
 *
 * @group API
 * @group Wikibase
 * @group WikibaseAPI
 * @group BreakingTheSlownessBarrier
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class SetLabelTest extends ModifyTermTestCase {

	/**
	 * @var bool
	 */
	private static $hasSetup;

	protected function setUp() : void {
		parent::setUp();

		self::$testAction = 'wbsetlabel';

		if ( !isset( self::$hasSetup ) ) {
			$this->initTestEntities( [ 'Empty' ] );
		}
		self::$hasSetup = true;
	}

	public function testGetAllowedParams_listsItemsAndProperties() {
		list( $result, ) = $this->doApiRequest(
			[
				'action' => 'paraminfo',
				'modules' => self::$testAction,
			]
		);
		$apiParams = $result['paraminfo']['modules'][0]['parameters'];

		$completedAssertions = false;
		foreach ( $apiParams as $paramDetails ) {
			if ( $paramDetails['name'] === 'new' ) {
				$this->assertContains( 'item', $paramDetails['type'] );
				$this->assertContains( 'property', $paramDetails['type'] );
				$completedAssertions = true;
			}
		}

		if ( !$completedAssertions ) {
			$this->fail( 'Failed to find and verify \'new\' parameter docs for wbsetlabel' );
		}
	}

	/**
	 * @dataProvider provideData
	 */
	public function testSetLabel( $params, $expected ) {
		self::doTestSetTerm( 'labels', $params, $expected );
	}

	/**
	 * @dataProvider provideExceptionData
	 */
	public function testSetLabelExceptions( $params, $expected, $token = true ) {
		self::doTestSetTermExceptions( $params, $expected, $token );
	}

	public function testSetLabelWithTag() {
		$this->assertCanTagSuccessfulRequest( $this->getCreateItemAndSetLabelRequestParams() );
	}

	public function testUserCanEditWhenTheyHaveSufficientPermission() {
		$userWithAllPermissions = $this->createUserWithGroup( 'all-permission' );

		$this->setMwGlobals( 'wgGroupPermissions', [
			'all-permission' => [ 'item-term' => true, ],
			'*' => [ 'read' => true, 'edit' => true, 'writeapi' => true ]
		] );

		$newItem = $this->createItemUsing( $userWithAllPermissions );

		list( $result, ) = $this->doApiRequestWithToken(
			$this->getSetLabelRequestParams( $newItem->getId() ),
			null,
			$userWithAllPermissions
		);

		$this->assertEquals( 1, $result['success'] );
	}

	public function testUserCannotSetLabelWhenTheyLackPermission() {
		$this->markTestSkipped( 'Disabled due to flakiness JDF 2019-03-19 T218378' );

		$userWithInsufficientPermissions = $this->createUserWithGroup( 'no-permission' );
		$userWithAllPermissions = $this->createUserWithGroup( 'all-permission' );

		$this->setMwGlobals( 'wgGroupPermissions', [
			'no-permission' => [ 'item-term' => false ],
			'all-permission' => [ 'item-term' => true, ],
			'*' => [ 'read' => true, 'edit' => true, 'writeapi' => true ]
		] );

		// And an item
		$newItem = $this->createItemUsing( $userWithAllPermissions );

		// Then the request is denied
		$expected = [
			'type' => ApiUsageException::class,
			'code' => 'permissiondenied'
		];

		$this->doTestQueryExceptions(
			$this->getSetLabelRequestParams( $newItem->getId() ),
			$expected,
			$userWithInsufficientPermissions
		);
	}

	public function testUserCanCreateItemWithLabelWhenTheyHaveSufficientPermissions() {
		$userWithAllPermissions = $this->createUserWithGroup( 'all-permission' );

		$this->setMwGlobals( 'wgGroupPermissions', [
			'all-permission' => [ 'item-term' => true, 'createpage' => true ],
			'*' => [ 'read' => true, 'edit' => true, 'writeapi' => true ]
		] );

		list( $result, ) = $this->doApiRequestWithToken(
			$this->getCreateItemAndSetLabelRequestParams(),
			null,
			$userWithAllPermissions
		);

		$this->assertEquals( 1, $result['success'] );
		$this->assertSame( 'a label', $result['entity']['labels']['en']['value'] );
	}

	public function testUserCannotCreateItemWhenTheyLackPermission() {
		$userWithInsufficientPermissions = $this->createUserWithGroup( 'no-permission' );

		$this->setMwGlobals( 'wgGroupPermissions', [
			'no-permission' => [ 'createpage' => false ],
			'*' => [ 'read' => true, 'edit' => true, 'item-term' => true, 'writeapi' => true ]
		] );

		MediaWikiServices::getInstance()->resetServiceForTesting( 'PermissionManager' );

		// Then the request is denied
		$expected = [
			'type' => ApiUsageException::class,
			'code' => 'permissiondenied'
		];

		$this->doTestQueryExceptions(
			$this->getCreateItemAndSetLabelRequestParams(),
			$expected,
			$userWithInsufficientPermissions
		);
	}

	/**
	 * @param User $user
	 *
	 * @return Item
	 */
	private function createItemUsing( User $user ) {
		$store = WikibaseRepo::getDefaultInstance()->getEntityStore();

		$itemRevision = $store->saveEntity( new Item(), 'SetSiteLinkTest', $user, EDIT_NEW );
		return $itemRevision->getEntity();
	}

	/**
	 * @param string $groupName
	 *
	 * @return User
	 */
	private function createUserWithGroup( $groupName ) {
		return $this->getTestUser( [ 'wbeditor', $groupName ] )->getUser();
	}

	private function getCreateItemAndSetLabelRequestParams() {
		return [
			'action' => 'wbsetlabel',
			'new' => 'item',
			'language' => 'en',
			'value' => 'a label',
		];
	}

	private function getSetLabelRequestParams( ItemId $id ) {
		return [
			'action' => 'wbsetlabel',
			'id' => $id->getSerialization(),
			'language' => 'en',
			'value' => 'other label',
		];
	}

}
