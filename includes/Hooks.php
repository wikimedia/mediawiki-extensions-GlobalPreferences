<?php

namespace GlobalPreferences;

use DatabaseUpdater;
use Linker;
use PreferencesForm;
use SpecialPage;
use User;

class Hooks {

	/**
	 * "bad" preferences that we should remove from
	 * Special:GlobalPrefs
	 * @var array
	 */
	protected static $prefsBlacklist = [
		// Stored in user table, doesn't work yet
		'realname',
		// @todo Show CA user id / shared user table id?
		'userid',
		// @todo Show CA global groups instead?
		'usergroups',
		// @todo Should global edit count instead?
		'editcount',
		'registrationdate',
	];

	/**
	 * Preference types that we should not add a checkbox for
	 * @var array
	 */
	protected static $typeBlacklist = [
		'info',
		'hidden',
		'api',
	];

	/**
	 * Preference classes that are allowed to be global
	 * @var array
	 */
	protected static $classWhitelist = [
		'HTMLSelectOrOtherField',
		'CirrusSearch\HTMLCompletionProfileSettings',
		'NewHTMLCheckField',
		'HTMLFeatureField',
	];

	/**
	 * @FIXME This is terrible
	 */
	public static function onExtensionFunctions() {
		global $wgHooks;
		// Register this as late as possible!
		$wgHooks['GetPreferences'][] = self::class . '::onGetPreferences';
	}

	/**
	 * Load our global prefs
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/UserLoadOptions
	 * @param User $user The user for whom options are being loaded.
	 * @param array &$options The user's options; can be modified.
	 * @return bool
	 */
	public static function onUserLoadOptions( User $user, &$options ) {
		$id = GlobalPreferences::getUserID( $user );
		if ( !$id ) {
			// Not a global user.
			return true;
		}

		$dbr = GlobalPreferences::getPrefsDB( DB_SLAVE );
		$res = $dbr->select(
			'global_preferences',
			[ 'gp_property', 'gp_value' ],
			[ 'gp_user' => $id ],
			__METHOD__
		);

		$user->mGlobalPrefs = [];
		$user->mLocalPrefs = [];

		foreach ( $res as $row ) {
			if ( isset( $user->mOptions[$row->gp_property] ) ) {
				// Store the local one we will override
				$user->mLocalPrefs[$row->gp_property] = $user->mOptions[$row->gp_property];
			}
			$options[$row->gp_property] = $row->gp_value;
			$user->mGlobalPrefs[] = $row->gp_property;
		}

		return true;
	}

	/**
	 * Don't save global prefs
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/UserSaveOptions
	 * @param User $user The user for whom options are being saved.
	 * @param array &$options The user's options; can be modified.
	 * @return bool
	 */
	public static function onUserSaveOptions( User $user, &$options ) {
		if ( GlobalPreferences::onGlobalPrefsPage() ) {
			// It shouldn't be possible to save local options here,
			// but never save on this page anyways.
			return false;
		}

		foreach ( $user->mGlobalPrefs as $pref ) {
			if ( isset( $options[$pref] ) ) {
				unset( $options[$pref] );
			}
			// But also save prefs we might have overrode...
			if ( isset( $user->mLocalPrefs[$pref] ) ) {
				$options[$pref] = $user->mLocalPrefs[$pref];
			}
		}

		return true;
	}

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/PreferencesFormPreSave
	 * @param array $formData An associative array containing the data from the preferences form.
	 * @param PreferencesForm $form The PreferencesForm object that represents the preferences form.
	 * @param User $user The User object that can be used to change the user's preferences.
	 * @param array &$result The boolean return value of the Preferences::tryFormSubmit method.
	 * @return bool
	 */
	public static function onPreferencesFormPreSave(
		array $formData,
		PreferencesForm $form,
		User $user,
		&$result
	) {
		if ( !GlobalPreferences::onGlobalPrefsPage( $form ) ) {
			// Don't interfere with local preferences
			return true;
		}

		$rows = [];
		$prefs = [];
		foreach ( $formData as $name => $value ) {
			if ( substr( $name, -strlen( 'global' ) ) === 'global' && $value === true ) {
				$realName = substr( $name, 0, -strlen( '-global' ) );
				if ( isset( $formData[$realName] ) && !in_array( $realName, self::$prefsBlacklist ) ) {
					$prefs[$realName] = $formData[$realName];
				} else {
					// FIXME: Handle checkbox matrixes properly
					/*
					var_dump($realName);
					var_dump($name);
					*/
				}
			}
		}

		$id = GlobalPreferences::getUserID( $user );
		foreach ( $prefs as $prop => $value ) {
			$rows[] = [
				'gp_user' => $id,
				'gp_property' => $prop,
				'gp_value' => $value,
			];

		}

		// Reset preferences, and then save new ones
		GlobalPreferences::resetGlobalUserSettings( $user );
		if ( $rows ) {
			$dbw = GlobalPreferences::getPrefsDB( DB_MASTER );
			$dbw->replace(
				'global_preferences',
				[ 'gp_user', 'gp_property' ],
				$rows,
				__METHOD__
			);
		}

		return false;
	}

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 * @param DatabaseUpdater $updater The database updater.
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		global $wgGlobalPreferencesDB;
		if ( is_null( $wgGlobalPreferencesDB ) || $wgGlobalPreferencesDB === wfWikiID() ) {
			// Only add the table if it's supposed to be on this wiki.
			$sqlPath = __DIR__ . '/../schema.sql';
			$updater->addExtensionTable( 'global_preferences', $sqlPath );
		}

		return true;
	}

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/GetPreferences
	 * @param User $user User whose preferences are being modified.
	 * @param array &$prefs Preferences description array, to be fed to an HTMLForm object.
	 * @return bool
	 */
	public static function onGetPreferences( User $user, &$prefs ) {
		if ( !GlobalPreferences::isUserGlobalized( $user ) ) {
			return true;
		}

		if ( GlobalPreferences::onGlobalPrefsPage() ) {
			if ( !isset( $user->mGlobalPrefs ) ) {
				// Just in case the user hasn't been loaded yet. Triggers User::loadOptions.
				$user->getOption( '' );
			}
			foreach ( $prefs as $name => $info ) {
				// FIXME: This whole code section sucks
				if ( !isset( $prefs["$name-global"] )
					&& self::isGlobalizablePreference( $name, $info )
				) {
					$prefs = wfArrayInsertAfter( $prefs, [
						"$name-global" => [
							'type' => 'toggle',
							'label-message' => 'globalprefs-check-label',
							'default' => in_array( $name, $user->mGlobalPrefs ),
							'section' => $info['section'],
							'cssclass' => 'mw-globalprefs-global-check',
						]
					], $name );
				} elseif ( in_array( $name, self::$prefsBlacklist ) ) {
					$prefs[$name]['type'] = 'hidden';
				}
			}
		} elseif ( GlobalPreferences::onLocalPrefsPage() ) {
			if ( !isset( $user->mGlobalPrefs ) ) {
				// Just in case the user hasn't been loaded yet. Triggers User::loadOptions.
				$user->getOption( '' );
			}
			foreach ( $user->mGlobalPrefs as $name ) {
				if ( isset( $prefs[$name] ) ) {
					$prefs[$name]['disabled'] = 'disabled';
					// Append a help message.
					$help = '';
					if ( isset( $prefs[$name]['help-message'] ) ) {
						$help .= wfMessage( $prefs[$name]['help-message'] )->parse() . '<br />';
					} elseif ( isset( $prefs[$name]['help'] ) ) {
						$help .= $prefs[$name]['help'] . '<br />';
					}

					$help .= wfMessage( 'globalprefs-set-globally' )->parse();
					$prefs[$name]['help'] = $help;
					unset( $prefs[$name]['help-message'] );

				}
			}
		}

		// Provide a link to Special:GlobalPreferences
		// if we're not on that page.
		if ( !GlobalPreferences::onGlobalPrefsPage() ) {
			$prefs['global-info'] = [
				'type' => 'info',
				'section' => 'personal/info',
				'label-message' => 'globalprefs-info-label',
				'raw' => true,
				'default' => Linker::link(
					SpecialPage::getTitleFor( 'GlobalPreferences' ),
					wfMessage( 'globalprefs-info-link' )->escaped()
				),
			];
		}

		return true;
	}

	/**
	 * Checks whether the given preference is localizable
	 *
	 * @param string $name Preference name
	 * @param array|mixed $info Preference description, by reference to avoid unnecessary cloning
	 * @return bool
	 */
	private static function isGlobalizablePreference( $name, &$info ) {
		$isAllowedType = isset( $info['type'] )
			&& !in_array( $info['type'], self::$typeBlacklist )
			&& !in_array( $name, self::$prefsBlacklist );

		$isAllowedClass = isset( $info['class'] )
			&& in_array( $info['class'], self::$classWhitelist );

		return substr( $name, -strlen( 'global' ) ) !== 'global'
			&& ( $isAllowedType || $isAllowedClass );
	}
}
