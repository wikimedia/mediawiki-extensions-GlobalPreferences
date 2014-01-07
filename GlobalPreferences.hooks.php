<?php

class GlobalPreferencesHooks {

	/**
	 * "bad" preferences that we should remove from
	 * Special:GlobalPrefs
	 * @var array
	 */
	static $badPrefs = array(
		'realname', // Stored in user table, doesn't work yet
		'userid', // @todo Show CA user id / shared user table id?
		'usergroups', // @todo Show CA global groups instead?
		'editcount', // @todo Should global edit count instead?
		'registrationdate',
	);

	/**
	 * Preference types that we should not add a checkbox for
	 * @var array
	 */
	static $badTypes = array(
		'info',
		'hidden',
		'api',
	);

	/**
	 * Load our global prefs
	 * @param User $user
	 * @param array $options
	 * @return bool
	 */
	public static function onUserLoadOptions( User $user, &$options ) {
		$id = GlobalPreferences::getUserID( $user );
		if ( !$id ) { // Not a global user :(
			return true;
		}

		if ( GlobalPreferences::onGlobalPrefsPage() ) {
			// Okay so don't let any local prefs show up on this page.
			$user->mOptions = User::getDefaultOptions();
		}

		$dbr = GlobalPreferences::getPrefsDB( DB_SLAVE );
		$res = $dbr->select(
			'global_preferences',
			array( 'gp_property', 'gp_value' ),
			array( 'gp_user' => $id ),
			__METHOD__
		);

		$user->mGlobalPrefs = array();
		$user->mLocalPrefs = array();

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
	 * @param User $user
	 * @param $options
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

	public static function onPreferencesFormSubmit( array $formData, PreferencesForm $form, User $user, &$result ) {
		if ( !GlobalPreferences::onGlobalPrefsPage( $form ) ) {
			// Don't interfere with local preferences
			return true;
		}

		$rows = array();
		$prefs = array();
		foreach ( $formData as $name => $value ) {
			if ( substr( $name, -strlen( 'global' ) ) === 'global' && $value === true ) {
				$realName = substr( $name, 0, -strlen( '-global' ) );
				if ( isset( $formData[$realName] ) && !in_array( $realName, self::$badPrefs ) ) {
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
			$rows[] = array(
				'gp_user' => $id,
				'gp_property' => $prop,
				'gp_value' => $value,
			);

		}

		// Reset preferences, and then save new ones
		GlobalPreferences::resetUserSettings( $user );
		if ( $rows ) {
			$dbw = GlobalPreferences::getPrefsDB( DB_MASTER );
			$dbw->replace(
				'global_preferences',
				array( 'gp_user', 'gp_property' ),
				$rows,
				__METHOD__
			);
		}

		return false;
	}

	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'global_preferences', __DIR__ . '/schema.sql' );

		return true;
	}

	public static function onGetPreferences( User $user, &$prefs ) {
		if ( !GlobalPreferences::isUserGlobalized( $user ) ) {
			return true;
		}

		if ( GlobalPreferences::onGlobalPrefsPage() ) {
			if ( !isset( $user->mGlobalPrefs ) ) {
				// Just in case the user hasn't been loaded yet.
				$user->getOption(''); // Triggers User::loadOptions
			}
			foreach ( $prefs as $name => $info ) {
				// FIXME: This whole code section sucks
				if ( isset( $info['type'] )
					&& substr( $name, -strlen( 'global' ) ) !== 'global'
					&& !isset( $prefs["$name-global"] )
					&& !in_array( $info['type'], self::$badTypes )
					&& !in_array( $name, self::$badPrefs )
				) {
					$prefs = wfArrayInsertAfter( $prefs, array(
						"$name-global" => array(
							'type' => 'toggle',
							'label-message' => 'globalprefs-check-label',
							'default' => in_array( $name, $user->mGlobalPrefs ),
							'section' => $info['section'],
						)
					), $name );
				} elseif ( in_array( $name, self::$badPrefs ) ) {
					$prefs[$name]['type'] = 'hidden';
				}
			}
		} elseif ( GlobalPreferences::onLocalPrefsPage() ) {
			if ( !isset( $user->mGlobalPrefs ) ) {
				// Just in case the user hasn't been loaded yet.
				$user->getOption(''); // Triggers User::loadOptions
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
			$prefs['global-info'] = array(
				'type' => 'info',
				'section' => 'personal/info',
				'label-message' => 'globalprefs-info-label',
				'raw' => true,
				'default' => Linker::link(
					SpecialPage::getTitleFor( 'GlobalPreferences' ),
					wfMessage( 'globalprefs-info-link' )->escaped()
				),
			);
		}

		return true;
	}
}