<?php

namespace GlobalPreferences;

use CentralIdLookup;
use DatabaseUpdater;
use Language;
use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;
use OutputPage;
use PreferencesForm;
use Skin;
use User;

class Hooks {

	protected static $userIdListPreferences = [
		'email-blacklist',
		'echo-notifications-blacklist',
	];

	/**
	 * Allows last minute changes to the output page, e.g. adding of CSS or JavaScript by extensions.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * @param OutputPage &$out The output page.
	 * @param Skin &$skin The skin. Not used.
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		if ( $out->getTitle()->isSpecial( 'Preferences' ) ) {
			// Add module styles and scripts separately
			// so non-JS users get the styles quicker and to avoid a FOUC.
			$out->addModuleStyles( 'ext.GlobalPreferences.local-nojs' );
			$out->addModules( 'ext.GlobalPreferences.local' );
		}
	}

	/**
	 * Load global preferences.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/UserLoadOptions
	 * @param User $user The user for whom options are being loaded.
	 * @param array &$options The user's options; can be modified.
	 */
	public static function onUserLoadOptions( User $user, &$options ) {
		/** @var GlobalPreferencesFactory $globalPreferences */
		$globalPreferences = MediaWikiServices::getInstance()->getPreferencesFactory();
		$globalPreferences->setUser( $user );
		if ( !$globalPreferences->isUserGlobalized() ) {
			// Not a global user.
			return;
		}

		// Overwrite all options that have a global counterpart.
		foreach ( $globalPreferences->getGlobalPreferencesValues() as $optName => $globalValue ) {
			// Don't overwrite if it has a local exception, unless we're just trying to get .
			if (
				!GlobalPreferencesForm::gettingGlobalOnly()
				&& $user->getOption( $optName . GlobalPreferencesFactory::LOCAL_EXCEPTION_SUFFIX )
			) {
				continue;
			}
			// Replicate any transformations that are done in User::loadOption()
			// and those for any other extensions that don't play nicely.
			// 1. Convert specific preferences from newline delimited strings to arrays of IDs.
			if ( in_array( $optName, static::$userIdListPreferences ) ) {
				$globalValue = array_map( 'intval', explode( "\n", $globalValue ) );
			}
			// 2. Convert '0' to 0. PHP's boolean conversion considers them both false,
			// but e.g. JavaScript considers the former as true.
			if ( $globalValue === '0' ) {
				$globalValue = 0;
			}
			$options[ $optName ] = $globalValue;
		}
	}

	/**
	 * When saving a user's options, remove any global ones and never save any on the Global
	 * Preferences page. Global options are saved separately, in the PreferencesFormPreSave hook.
	 * @param User $user The user. Not used.
	 * @param string[] &$options The user's options.
	 * @return bool False if nothing changed, true otherwise.
	 */
	public static function onUserSaveOptions( User $user, &$options ) {
		/** @var GlobalPreferencesFactory $preferencesFactory */
		$preferencesFactory = MediaWikiServices::getInstance()->getPreferencesFactory();
		$preferencesFactory->setUser( $user );
		if ( $preferencesFactory->onGlobalPrefsPage() ) {
			// It shouldn't be possible to save local options here,
			// but never save on this page anyways.
			return false;
		}

		foreach ( $options as $optName => $optVal ) {
			// Ignore if ends in "-global".
			if ( GlobalPreferencesFactory::isGlobalPrefName( $optName ) ) {
				unset( $options[ $optName ] );
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
		/** @var GlobalPreferencesFactory $preferencesFactory */
		$preferencesFactory = MediaWikiServices::getInstance()->getPreferencesFactory();
		if ( !$preferencesFactory->onGlobalPrefsPage( $form ) ) {
			// Don't interfere with local preferences
			return true;
		}

		$prefs = [];
		foreach ( $formData as $name => $value ) {
			// If this is the '-global' counterpart to a preference.
			if ( GlobalPreferencesFactory::isGlobalPrefName( $name ) && $value === true ) {
				// Determine the real name of the preference.
				$suffixLen = strlen( GlobalPreferencesFactory::GLOBAL_EXCEPTION_SUFFIX );
				$realName = substr( $name, 0, -$suffixLen );
				if ( isset( $formData[$realName] ) ) {
					// Replicate any transformations that are done in User::saveOptions().
					if ( in_array( $realName, static::$userIdListPreferences ) ) {
						// Some preferences are strings of newline-delimited user names.
						$lookup = CentralIdLookup::factory();
						$values = explode( "\n", $formData[$realName] );
						$ids = $lookup->centralIdsFromNames( $values, $user );
						$prefs[$realName] = implode( "\n", $ids );
					} else {
						// Store normal preference values.
						$prefs[$realName] = $formData[$realName];
					}

				} else {
					// If the real-named preference isn't set, this must be a CheckMatrix value
					// where the preference names are of the form "$realName-$column-$row"
					// (we also have to remove the "$realName-global" entry).
					$checkMatrix = preg_grep( "/^$realName/", array_keys( $formData ) );
					unset( $checkMatrix[ array_search( $name, $checkMatrix ) ] );
					$checkMatrixVals = array_intersect_key( $formData, array_flip( $checkMatrix ) );
					$prefs = array_merge( $prefs, $checkMatrixVals );
					// Also store a global $realName preference for benefit of the the
					// 'globalize-this' checkbox.
					$prefs[ $realName ] = true;

				}
			}
		}

		/** @var GlobalPreferencesFactory $preferencesFactory */
		$preferencesFactory = MediaWikiServices::getInstance()->getPreferencesFactory();
		$preferencesFactory->setUser( $user );
		$preferencesFactory->setGlobalPreferences( $prefs );

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
			$sqlPath = dirname( __DIR__ ) . '/sql';
			$updater->addExtensionTable( 'global_preferences', "$sqlPath/tables.sql" );
			$updater->dropExtensionIndex( 'global_preferences',
				'global_preferences_user_property',
				"$sqlPath/patch_primary_index.sql"
			);
		}

		return true;
	}

	/**
	 * Replace the PreferencesFactory service with the GlobalPreferencesFactory.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/MediaWikiServices
	 * @param MediaWikiServices $services The services object to use.
	 */
	public static function onMediaWikiServices( MediaWikiServices $services ) {
		$services->redefineService( 'PreferencesFactory', function ( MediaWikiServices $services ) {
			global $wgContLang, $wgLanguageCode;
			$wgContLang = Language::factory( $wgLanguageCode );
			$wgContLang->initContLang();
			$authManager = AuthManager::singleton();
			$linkRenderer = $services->getLinkRendererFactory()->create();
			$config = $services->getMainConfig();
			return new GlobalPreferencesFactory(
				$config, $wgContLang, $authManager, $linkRenderer
			);
		} );
		// Now instantiate the new Preferences, to prevent it being overwritten.
		$services->getPreferencesFactory();
	}
}
