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
	const TABLE_NAME = 'global_preferences';

	/** Update this constant when making incompatible changes to caching */
	const CACHE_VERSION = 1;

	/** Cache lifetime */
	const CACHE_TTL = IExpiringStore::TTL_WEEK;

	/** Instructs pereference loading code to load the preferences from cache directly */
	const SKIP_CACHE = true;

	/** @var int The global user ID. */
	protected $userId;

	/**
	 * Create a new Global Preferences Storage object for a given user.
	 * @param int $userId The global user ID.
	 */
	public function __construct( $userId ) {
		$this->userId = $userId;
	}

	/**
	 * Get the user's global preferences.
	 *
	 * @param bool $skipCache Whether the preferences should be loaded strictly from DB
	 * @return string[] Keyed by the preference name.
	 */
	public function load( $skipCache = false ) {
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
	 * @return string[]
	 */
	protected function loadFromDB() {
		$dbr = $this->getDatabase( DB_REPLICA );
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
	 */
	public function save( $newPrefs, $knownPrefs ) {
		// Assemble the records to save.
		$rows = [];
		foreach ( $newPrefs as $prop => $value ) {
			$rows[] = [
				'gp_user' => $this->userId,
				'gp_property' => $prop,
				'gp_value' => $value,
			];
		}
		// Delete all global preferences, and then save new ones.
		$this->delete( $knownPrefs );
		if ( $rows ) {
			$dbw = $this->getDatabase( DB_MASTER );
			$dbw->replace(
				static::TABLE_NAME,
				[ 'gp_user', 'gp_property' ],
				$rows,
				__METHOD__
			);
		}
		$key = $this->getCacheKey();
		// Because we don't have the full preferences, just clear the cache
		$this->getCache()->delete( $key );
	}

	/**
	 * Delete all of this user's global preferences.
	 * @param string[] $knownPrefs Only delete the preferences we know about.
	 */
	public function delete( $knownPrefs = null ) {
		$db = $this->getDatabase( DB_MASTER );
		$conds = [ 'gp_user' => $this->userId ];
		if ( is_array( $knownPrefs ) ) {
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
	protected function getDatabase( $type = DB_REPLICA ) {
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
	protected function getCache() {
		return MediaWikiServices::getInstance()->getMainWANObjectCache();
	}

	/**
	 * @return string
	 */
	protected function getCacheKey() {
		return $this->getCache()
			->makeGlobalKey( 'globalpreferences', 'prefs', self::CACHE_VERSION, $this->userId );
	}

	/**
	 * @param mixed $preferences
	 */
	protected function saveToCache( $preferences ) {
		$key = $this->getCacheKey();
		$this->getCache()->set( $key, $preferences, self::CACHE_TTL );
	}
}
