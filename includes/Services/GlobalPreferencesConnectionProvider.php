<?php

namespace GlobalPreferences\Services;

use MediaWiki\Config\ServiceOptions;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * A service used to fetch a database connection to the GlobalPreferences database.
 */
class GlobalPreferencesConnectionProvider {

	public const CONSTRUCTOR_OPTIONS = [
		'GlobalPreferencesDB',
		'SharedDB',
	];

	private ServiceOptions $options;
	private IConnectionProvider $dbProvider;

	public function __construct( ServiceOptions $options, IConnectionProvider $dbProvider ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->dbProvider = $dbProvider;
	}

	/**
	 * @return false|string The domain ID of the GlobalPreferences database
	 */
	private function getDomainId() {
		$globalPreferencesDB = (string)$this->options->get( 'GlobalPreferencesDB' );
		$sharedDB = (string)$this->options->get( 'SharedDB' );
		if ( $globalPreferencesDB ) {
			return $globalPreferencesDB;
		} elseif ( $sharedDB ) {
			return $sharedDB;
		}
		// local wiki
		return false;
	}

	/**
	 * Get a replica GlobalPreferences database connection.
	 *
	 * @since 1.44
	 *
	 * @return IReadableDatabase
	 */
	public function getReplicaDatabase(): IReadableDatabase {
		return $this->dbProvider->getReplicaDatabase( $this->getDomainId() );
	}

	/**
	 * Get a primary GlobalPreferences database connection.
	 *
	 * @since 1.44
	 *
	 * @return IDatabase
	 * @internal You should probably be using the API or special page to update a user's global preference value.
	 */
	public function getPrimaryDatabase(): IDatabase {
		return $this->dbProvider->getPrimaryDatabase( $this->getDomainId() );
	}
}
