<?php
/**
 * This file contains only the Storage class.
 * @package GlobalPreferences
 */

namespace GlobalPreferences;

use Wikimedia\Rdbms\Database;

/**
 * This class handles all database storage of global preferences.
 * @package GlobalPreferences
 */
class Storage {

	/** The non-prefixed name of the global preferences database table. */
	const TABLE_NAME = 'global_preferences';

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
	 * @return string[] Keyed by the preference name.
	 */
	public function load() {
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
	 */
	public function save( $newPrefs ) {
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
		$this->delete();
		if ( $rows ) {
			$dbw = $this->getDatabase( DB_MASTER );
			$dbw->replace(
				static::TABLE_NAME,
				[ 'gp_user', 'gp_property' ],
				$rows,
				__METHOD__
			);
		}
	}

	/**
	 * Delete all of this user's global preferences.
	 */
	public function delete() {
		$db = $this->getDatabase( DB_MASTER );
		$db->delete(
			static::TABLE_NAME,
			[ 'gp_user' => $this->userId ],
			__METHOD__
		);
	}

	/**
	 * Get the database object pointing to the Global Preferences database.
	 * @param int $type One of the DB_* constants
	 * @return Database
	 */
	protected function getDatabase( $type = DB_REPLICA ) {
		global $wgGlobalPreferencesDB;
		if ( $wgGlobalPreferencesDB ) {
			return wfGetDB( $type, [], $wgGlobalPreferencesDB );
		} else {
			return wfGetDB( $type );
		}
	}
}
