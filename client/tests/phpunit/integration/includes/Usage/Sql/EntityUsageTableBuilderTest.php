<?php

namespace Wikibase\Client\Tests\Integration\Usage\Sql;

use Onoi\MessageReporter\MessageReporter;
use PHPUnit\Framework\MockObject\Matcher\Invocation;
use Wikibase\Client\Usage\Sql\EntityUsageTable;
use Wikibase\Client\Usage\Sql\EntityUsageTableBuilder;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\Lib\Reporting\ExceptionHandler;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLBFactory;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLoadBalancer;

/**
 * @covers \Wikibase\Client\Usage\Sql\EntityUsageTableBuilder
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseUsageTracking
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class EntityUsageTableBuilderTest extends \MediaWikiTestCase {

	protected function setUp() : void {
		$this->tablesUsed[] = EntityUsageTable::DEFAULT_TABLE_NAME;
		$this->tablesUsed[] = 'page_props';

		parent::setUp();
	}

	public function testFillUsageTable() {
		$this->putWikidataItemPageProps( [
			11 => 'Q11',
			22 => 'Q22',
			33 => 'Q33',
			44 => 'Q44',
			88 => '',
			99 => '--broken--',
		] );

		$primer = new EntityUsageTableBuilder(
			new ItemIdParser(),
			new FakeLBFactory( [ 'lb' => new FakeLoadBalancer( [ 'dbr' => $this->db ] ) ] ),
			2
		);
		$primer->setProgressReporter( $this->getMessageReporter( $this->exactly( 3 ) ) );
		$primer->setExceptionHandler( $this->getExceptionHandler( $this->exactly( 2 ) ) );

		$primer->fillUsageTable();

		$actual = $this->fetchAllUsageStrings();
		ksort( $actual );

		$expected = [
			11 => 'Q11#X',
			22 => 'Q22#X',
			33 => 'Q33#X',
			44 => 'Q44#X',
		];

		$this->assertEquals( $expected, $actual );
	}

	private function putWikidataItemPageProps( array $entries ) {
		$db = wfGetDB( DB_MASTER );

		$db->startAtomic( __METHOD__ );

		foreach ( $entries as $pageId => $entityId ) {
			$db->insert( 'page_props', [
				'pp_page' => (int)$pageId,
				'pp_propname' => 'wikibase_item',
				'pp_value' => (string)$entityId
			], __METHOD__ );
		}

		$db->endAtomic( __METHOD__ );
	}

	private function fetchAllUsageStrings() {
		$db = wfGetDB( DB_MASTER );

		$res = $db->select( EntityUsageTable::DEFAULT_TABLE_NAME, '*', '', __METHOD__ );

		$usages = [];
		foreach ( $res as $row ) {
			$key = (int)$row->eu_page_id;

			$usages[$key] = $row->eu_entity_id . '#' . $row->eu_aspect;
		}

		return $usages;
	}

	/**
	 * @param Invocation $matcher
	 *
	 * @return ExceptionHandler
	 */
	private function getExceptionHandler( $matcher ) {
		$mock = $this->createMock( ExceptionHandler::class );
		$mock->expects( $matcher )
			->method( 'handleException' );

		return $mock;
	}

	/**
	 * @param Invocation $matcher
	 *
	 * @return MessageReporter
	 */
	private function getMessageReporter( $matcher ) {
		$mock = $this->createMock( MessageReporter::class );
		$mock->expects( $matcher )
			->method( 'reportMessage' );

		return $mock;
	}

}
