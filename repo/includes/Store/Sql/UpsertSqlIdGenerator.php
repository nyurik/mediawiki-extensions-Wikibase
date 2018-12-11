<?php

namespace Wikibase;

use MWException;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LoadBalancer;

/**
 * Unique Id generator implemented using an SQL table and an UPSERT query.
 * The table needs to have the fields id_value and id_type.
 *
 * The UPSERT approach was created in https://phabricator.wikimedia.org/T194299
 * as wikidata.org was having issues with the old SqlIdGenerator.
 *
 * LAST_INSERT_ID from mysql is used in this class, which means that this IdGenerator
 * can only be used with MySQL.
 * This class depends on the upsert implementation within the RDBMS library for
 * different DB backends.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class UpsertSqlIdGenerator implements IdGenerator {

	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer;

	/**
	 * @var int[]
	 */
	private $idBlacklist;

	/**
	 * Limit for id generation attempts that hit the blacklist.
	 * We have not had any blacklists in the past with anywhere near this number of sequential entity ids.
	 * @var int
	 */
	private $blacklistAttempts = 10;

	/**
	 * @param LoadBalancer $loadBalancer
	 * @param array[] $idBlacklist
	 */
	public function __construct( LoadBalancer $loadBalancer, array $idBlacklist = [] ) {
		$this->loadBalancer = $loadBalancer;
		$this->idBlacklist = $idBlacklist;
	}

	/**
	 * @see IdGenerator::getNewId
	 *
	 * @param string $type normally is content model id (e.g. wikibase-item or wikibase-property)
	 *
	 * @return int
	 */
	public function getNewId( $type ) {
		$database = $this->loadBalancer->getConnection( DB_MASTER );

		$idGenerations = 0;
		do {
			if ( $idGenerations >= $this->blacklistAttempts ) {
				throw new MWException(
					"Could not generate a non blacklisted ID of type '{$type}', tried {$this->blacklistAttempts} times."
				);
			}
			$id = $this->generateNewId( $database, $type );
			$idGenerations++;

		} while ( $this->idIsOnBlacklist( $type, $id ) );

		$this->loadBalancer->reuseConnection( $database );

		return $id;
	}

	private function idIsOnBlacklist( $type, $id ) {
		return array_key_exists( $type, $this->idBlacklist ) && in_array( $id, $this->idBlacklist[$type] );
	}

	/**
	 * Generates and returns a new ID.
	 *
	 * @param IDatabase $database
	 * @param string $type
	 *
	 * @throws MWException
	 * @return int
	 */
	private function generateNewId( IDatabase $database, $type ) {
		$database->startAtomic( __METHOD__ );

		$success = $this->upsertId( $database, $type );

		// Retry once
		if ( !$success ) {
			$success = $this->upsertId( $database, $type );
		}

		if ( !$success ) {
			throw new MWException( 'Could not generate a reliably unique ID.' );
		}

		$id = $database->insertId();

		$database->endAtomic( __METHOD__ );

		// If the upsert successfully inserts, we won't have an auto increment ID, instead it will be the 1 set in the query.
		if ( !is_int( $id ) || $id === 0 ) {
			$id = 1;
		}

		return $id;
	}

	/**
	 * @param IDatabase $database
	 * @param string $type
	 * @return bool Query success
	 */
	private function upsertId( IDatabase $database, $type ) {
		return $database->upsert(
			'wb_id_counters',
			[
				'id_type' => $type,
				'id_value' => 1,
			],
			[ 'id_value' ],
			[ 'id_value = LAST_INSERT_ID(id_value + 1)' ],
			__METHOD__
		);
	}

}
