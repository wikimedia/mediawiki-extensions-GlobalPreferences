<?php
/**
 * Implements global preferences for MediaWiki
 *
 * @author Kunal Mehta <legoktm@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @file
 * @ingroup Extensions
 *
 * Partially based off of work by Werdna
 * https://www.mediawiki.org/wiki/Special:Code/MediaWiki/49790
 */
class GlobalPreferences {

	/**
	 * @param int $type one of the DB_* constants
	 * @return DatabaseBase
	 */
	public static function getPrefsDB( $type = DB_SLAVE ) {
		global $wgGlobalPreferencesDB;
		if ( $wgGlobalPreferencesDB ) {
			return wfGetDB( $type, array(), $wgGlobalPreferencesDB );
		} else {
			return wfGetDB( $type );
		}
	}

	/**
	 * Checks if the user is globalized
	 * @param User $user
	 * @return bool
	 */
	public static function isUserGlobalized( User $user ) {
		if ( $user->isAnon() ) {
			// No prefs for anons, sorry :(
			return false;
		}
		if ( class_exists( 'CentralAuthUser' ) ) {
			$caUser = CentralAuthUser::getInstance( $user );
			return $caUser->exists();
		}

		// Assume that we're using shared user tables...
		return true;
	}

	/**
	 * Gets the user's ID that we're using in the table
	 * Returns 0 if the user is not global
	 * @param User $user
	 * @return int
	 */
	public static function getUserID( User $user ) {
		if ( !self::isUserGlobalized( $user ) ) {
			return 0;
		}
		if ( class_exists( 'CentralAuthUser' ) ) {
			$caUser = CentralAuthUser::getInstance( $user );
			return $caUser->getId();
		} else {
			// shared user tables use the same user_id
			return $user->getId();
		}
	}

	/**
	 * Deletes all of a user's global prefs
	 * Assumes that the user is globalized
	 * @param User $user
	 */
	public static function resetGlobalUserSettings( User $user ) {
		if ( !isset( $user->mGlobalPrefs ) ) {
			$user->getOption( '' ); // Trigger loading
		}
		if ( count( $user->mGlobalPrefs ) ) {
			self::getPrefsDB( DB_MASTER )->delete(
				'global_preferences',
				array( 'gp_user' => self::getUserID( $user ) ),
				__METHOD__
			);
		}
	}

	/**
	 * Convenience function to check if we're on the global
	 * prefs page
	 * @param IContextSource $context
	 * @return bool
	 */
	public static function onGlobalPrefsPage( $context = null ) {
		$context = $context ?: RequestContext::getMain();
		return $context->getTitle()
		&& $context->getTitle()->isSpecial( 'GlobalPreferences' );
	}

	/**
	 * Convenience function to check if we're on the local
	 * prefs page
	 * @param IContextSource $context
	 * @return bool
	 */
	public static function onLocalPrefsPage( $context = null ) {
		$context = $context ?: RequestContext::getMain();
		return $context->getTitle()
		&& $context->getTitle()->isSpecial( 'Preferences' );
	}
}
