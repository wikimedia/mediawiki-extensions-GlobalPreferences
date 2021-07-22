<?php
/**
 * This file contains only the Storage class.
 * @package GlobalPreferences
 */

namespace GlobalPreferences;

use IExpiringStore;
use MediaWiki\MediaWikiServices;
use WANObjectCache;
use Wikimedia\Rdbms\IDatabase;

/**
 * This class handles all database storage of global preferences.
 * @package GlobalPreferences
 */
class Storage {

	/** The non-prefixed name of the global preferences database table. */
	public const TABLE_NAME = 'global_preferences';

	/** Update this constant when making incompatible changes to caching */
	private const CACHE_VERSION = 1;

	/** Cache lifetime */
	private const CACHE_TTL = IExpiringStore::TTL_WEEK;

	/** Instructs preference loading code to load the preferences from cache directly */
	public const SKIP_CACHE = true;

	/** @var int The global user ID. */
	protected $userId;

	/**
	 * Create a new Global Preferences Storage object for a given user.
	 * @param int $userId The global user ID.
	 */
	public function __construct( int $userId ) {
		$this->userId = $userId;
	}

	/**
	 * Get the user's global preferences.
	 *
	 * @param bool $skipCache Whether the preferences should be loaded strictly from DB
	 * @return string[] Keyed by the preference name.
	 */
	public function load( bool $skipCache = false ): array {
		if ( $skipCache ) {
			return $this->loadFromDB();
		}

		$cache = $this->getCache();
		$key = $this->getCacheKey();

		return $cache->getWithSetCallback( $key, self::CACHE_TTL, function () {
			return $this->loadFromDB();
		} );
	}

	/**
	 * @param int $dbType One of DB_* constants
	 * @return string[]
	 */
	protected function loadFromDB( $dbType = DB_REPLICA ): array {
		$dbr = $this->getDatabase( $dbType );
		$res = $dbr->select(
			static::TABLE_NAME,
			[ 'gp_property', 'gp_value' ],
			[ 'gp_user' => $this->userId ],
			__METHOD__
		);
		$preferences = [];
		foreach ( $res as $row ) {
			$preferences[$row->gp_property] = $row->gp_value;
		}
		return $preferences;
	}

	/**
	 * Save a set of global preferences. All existing preferences will be deleted before the new
	 * ones are saved.
	 * @param string[] $newPrefs Keyed by the preference name.
	 * @param string[] $knownPrefs Only work with the preferences we know about.
	 * @param string[] $checkMatricesToClear List of check matrix controls that
	 *        need their rows purged
	 */
	public function save( array $newPrefs, array $knownPrefs, array $checkMatricesToClear = []
	): void {
		$currentPrefs = $this->loadFromDB( DB_PRIMARY );

		// Find records needing an insert or update
		$save = [];
		$delete = [];
		foreach ( $newPrefs as $prop => $value ) {
			if ( $value !== null ) {
				if ( !isset( $currentPrefs[$prop] ) || $currentPrefs[$prop] != $value ) {
					$save[$prop] = $value;
				}
			} else {
				$delete[] = $prop;
			}
		}

		// Assemble the records to save
		$rows = [];
		foreach ( $save as $prop => $value ) {
			$rows[] = [
				'gp_user' => $this->userId,
				'gp_property' => $prop,
				'gp_value' => $value,
			];
		}
		// Save
		if ( $rows ) {
			$dbw = $this->getDatabase( DB_PRIMARY );
			$dbw->replace(
				static::TABLE_NAME,
				[ [ 'gp_user', 'gp_property' ] ],
				$rows,
				__METHOD__
			);
		}

		// Delete unneeded rows
		$keys = array_keys( $currentPrefs );
		// Only delete prefs present on the local wiki
		$keys = array_intersect( $keys, $knownPrefs );
		$keys = array_values( array_diff( $keys, array_keys( $newPrefs ) ) );
		$delete = array_merge( $delete, $keys );

		// And specifically nuke the rows of a deglobalized CheckMatrix
		foreach ( $checkMatricesToClear as $matrix ) {
			foreach ( array_keys( $currentPrefs ) as $pref ) {
				if ( strpos( $pref, $matrix ) === 0 ) {
					$delete[] = $pref;
				}
			}
		}
		$delete = array_unique( $delete );

		if ( $delete ) {
			$this->delete( $delete );
		}

		$key = $this->getCacheKey();
		// Because we don't have the full preferences, just clear the cache
		$this->getCache()->delete( $key );
	}

	/**
	 * Delete all of this user's global preferences.
	 * @param string[]|null $knownPrefs Only delete the preferences we know about.
	 */
	public function delete( ?array $knownPrefs = null ): void {
		$db = $this->getDatabase( DB_PRIMARY );
		$conds = [ 'gp_user' => $this->userId ];
		if ( $knownPrefs !== null ) {
			$conds['gp_property'] = $knownPrefs;
		}
		$db->delete( static::TABLE_NAME, $conds, __METHOD__ );
		$key = $this->getCacheKey();
		$this->getCache()->delete( $key );
	}

	/**
	 * Get the database object pointing to the Global Preferences database.
	 * @param int $type One of the DB_* constants
	 * @return IDatabase
	 */
	protected function getDatabase( int $type = DB_REPLICA ): IDatabase {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$globalPreferencesDB = (string)$config->get( 'GlobalPreferencesDB' );
		$sharedDB = (string)$config->get( 'SharedDB' );
		$lbf = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		if ( $globalPreferencesDB != '' ) {
			$domainId = $globalPreferencesDB;
		} elseif ( $sharedDB != '' ) {
			$domainId = $sharedDB;
		} else {
			// local wiki
			$domainId = false;
		}

		return $lbf->getMainLB( $domainId )->getConnectionRef( $type, [], $domainId );
	}

	/**
	 * @return WANObjectCache
	 */
	protected function getCache(): WANObjectCache {
		return MediaWikiServices::getInstance()->getMainWANObjectCache();
	}

	/**
	 * @return string
	 */
	protected function getCacheKey(): string {
		return $this->getCache()
			->makeGlobalKey( 'globalpreferences', 'prefs', self::CACHE_VERSION, $this->userId );
	}
}
