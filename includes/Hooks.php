<?php

namespace GlobalPreferences;

use ApiOptions;
use DatabaseUpdater;
use HTMLForm;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use Message;
use OutputPage;
use Skin;
use User;
use Wikimedia\Rdbms\IDatabase;

class Hooks {

	/**
	 * Allows last minute changes to the output page, e.g. adding of CSS or JavaScript by extensions.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * @param OutputPage &$out The output page.
	 * @param Skin &$skin The skin. Not used.
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ): void {
		if ( $out->getTitle()->isSpecial( 'Preferences' ) ) {
			// Add module styles and scripts separately
			// so non-JS users get the styles quicker and to avoid a FOUC.
			$out->addModuleStyles( 'ext.GlobalPreferences.local-nojs' );
			$out->addModules( 'ext.GlobalPreferences.local' );
		}
	}

	/**
	 * Load global preferences.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/LoadUserOptions
	 * @param UserIdentity $user The user for whom options are being loaded.
	 * @param array &$options The user's options; can be modified.
	 */
	public static function onLoadUserOptions( UserIdentity $user, array &$options ) {
		$globalPreferences = self::getPreferencesFactory();
		if ( !$globalPreferences->isUserGlobalized( $user ) ) {
			// Not a global user.
			return;
		}

		$logger = LoggerFactory::getInstance( 'preferences' );
		$logger->debug(
			'Loading global options for user \'{user}\'',
			[ 'user' => $user->getName() ]
		);
		// Overwrite all options that have a global counterpart.
		$globalPrefs = $globalPreferences->getGlobalPreferencesValues( $user );
		if ( $globalPrefs === false ) {
			return;
		}
		foreach ( $globalPrefs as $optName => $globalValue ) {
			// Don't overwrite if it has a local exception.
			$localExceptionName = $optName . GlobalPreferencesFactory::LOCAL_EXCEPTION_SUFFIX;
			if ( isset( $options[ $localExceptionName ] ) && $options[ $localExceptionName ] ) {
				continue;
			}

			// FIXME: temporary plug for T201340: DB might have rows for deglobalized
			// Echo notifications. Don't allow these through if the main checkbox is not checked.
			if ( !( $globalPrefs['echo-subscriptions'] ?? false )
				&& strpos( $optName, 'echo-subscriptions-' ) === 0
			) {
				continue;
			}

			// Convert '0' to 0. PHP's boolean conversion considers them both false,
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
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SaveUserOptions
	 * @param UserIdentity $user The user.
	 * @param string[] &$modifiedOptions The user's options that were modified.
	 * @param string[] $originalOptions The original options.
	 * @return bool False if nothing changed, true otherwise.
	 */
	public static function onSaveUserOptions( UserIdentity $user, array &$modifiedOptions, array $originalOptions ) {
		$preferencesFactory = self::getPreferencesFactory();
		if ( $preferencesFactory->onGlobalPrefsPage() ) {
			// It shouldn't be possible to save local options here,
			// but never save on this page anyways.
			return false;
		}

		$preferencesFactory->handleLocalPreferencesChange( $user, $modifiedOptions, $originalOptions );

		return true;
	}

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/PreferencesFormPreSave
	 * @param array $formData An associative array containing the data from the preferences form.
	 * @param HTMLForm $form The HTMLForm object that represents the preferences form.
	 * @param User $user The User object that can be used to change the user's preferences.
	 * @param array &$result The boolean return value of the Preferences::tryFormSubmit method.
	 * @return bool
	 */
	public static function onPreferencesFormPreSave(
		array $formData,
		HTMLForm $form,
		User $user,
		&$result
	): bool {
		$preferencesFactory = self::getPreferencesFactory();
		if ( !$preferencesFactory->onGlobalPrefsPage( $form ) ) {
			return self::localPreferencesFormPreSave( $formData, $user );
		}
		return true;
	}

	/**
	 * Process PreferencesFormPreSave for Special:Preferences
	 * Handles CheckMatrix
	 *
	 * @param array $formData Associative array of [ preference name => value ]
	 * @param User $user Current user
	 * @return bool Hook return value
	 */
	private static function localPreferencesFormPreSave( array $formData, User $user ): bool {
		$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
		foreach ( $formData as $pref => $value ) {
			if ( !GlobalPreferencesFactory::isLocalPrefName( $pref ) ) {
				continue;
			}
			// Determine the real name of the preference.
			$suffixLen = strlen( GlobalPreferencesFactory::LOCAL_EXCEPTION_SUFFIX );
			$realName = substr( $pref, 0, -$suffixLen );
			if ( isset( $formData[$realName] ) || !$value ) {
				// Not a checked CheckMatrix
				continue;
			}
			$checkMatrix = preg_grep( "/^$realName-/", array_keys( $formData ) );
			foreach ( $checkMatrix as $check ) {
				$localExceptionName = $check . GlobalPreferencesFactory::LOCAL_EXCEPTION_SUFFIX;
				$userOptionsManager->setOption( $user, $localExceptionName, true );
			}
		}
		return true;
	}

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 * @param DatabaseUpdater $updater The database updater.
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ): void {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$dBname = $config->get( 'DBname' );
		$sharedDB = $config->get( 'SharedDB' );

		// During install, extension registry config is not loaded - T198330
		$globalPreferencesDB = $config->has( 'GlobalPreferencesDB' )
			? $config->get( 'GlobalPreferencesDB' )
			: null;

		// Only add the global_preferences table to the $wgGlobalPreferencesDB or the $wgSharedDB,
		// unless neither of them is set. See also \GlobalPreferences\Storage::getDatabase().
		if ( ( $globalPreferencesDB === null && $sharedDB === null )
			|| $dBname === $globalPreferencesDB
			|| ( $globalPreferencesDB === null && $dBname === $sharedDB )
		) {
			$type = $updater->getDB()->getType();
			$sqlPath = dirname( __DIR__ ) . '/sql';

			$updater->addExtensionTable( 'global_preferences', "$sqlPath/$type/tables-generated.sql" );

			if ( $type === 'mysql' || $type === 'sqlite' ) {
				$updater->dropExtensionIndex( 'global_preferences',
					'global_preferences_user_property',
					"$sqlPath/patch_primary_index.sql"
				);
				$updater->modifyExtensionField( 'global_preferences',
					'gp_user',
					"$sqlPath/$type/patch-gp_user-unsigned.sql"
				);
			}
		}
	}

	/**
	 * Replace the PreferencesFactory service with the GlobalPreferencesFactory.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/MediaWikiServices
	 * @param MediaWikiServices $services The services object to use.
	 */
	public static function onMediaWikiServices( MediaWikiServices $services ): void {
		$services->redefineService( 'PreferencesFactory', static function ( MediaWikiServices $services ) {
			$mainConfig = $services->getMainConfig();
			$config = new ServiceOptions( GlobalPreferencesFactory::CONSTRUCTOR_OPTIONS,
				$mainConfig
			);
			$factory = new GlobalPreferencesFactory(
				$config,
				$services->getContentLanguage(),
				$services->getAuthManager(),
				$services->getLinkRendererFactory()->create(),
				$services->getNamespaceInfo(),
				$services->getPermissionManager(),
				$services->getLanguageConverterFactory()->getLanguageConverter(),
				$services->getLanguageNameUtils(),
				$services->getHookContainer(),
				$services->getUserOptionsLookup()
			);
			$factory->setLogger( LoggerFactory::getInstance( 'preferences' ) );
			$factory->setAutoGlobals( $mainConfig->get( 'GlobalPreferencesAutoPrefs' ) );

			return $factory;
		} );
	}

	/**
	 * Prevent local exception preferences from being cleaned up.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/DeleteUnknownPreferences
	 * @param string[] &$where Array of where clause conditions to add to.
	 * @param IDatabase $db
	 */
	public static function onDeleteUnknownPreferences( &$where, IDatabase $db ): void {
		$like = $db->buildLike( $db->anyString(), GlobalPreferencesFactory::LOCAL_EXCEPTION_SUFFIX );
		$where[] = "up_property NOT $like";
	}

	/**
	 * @param ApiOptions $apiModule
	 * @param User $user
	 * @param array $changes
	 */
	public static function onApiOptions( ApiOptions $apiModule, User $user,
		array $changes
	): void {
		// Only hook to the core module but not to our code that inherits from it
		if ( $apiModule->getModuleName() !== 'options' ) {
			return;
		}

		$factory = self::getPreferencesFactory();
		$globalPrefs = $factory->getGlobalPreferencesValues( $user );

		$toWarn = [];
		foreach ( array_keys( $changes ) as $preference ) {
			if ( GlobalPreferencesFactory::isLocalPrefName( $preference ) ) {
				continue;
			}
			$exceptionName = $preference . GlobalPreferencesFactory::LOCAL_EXCEPTION_SUFFIX;
			if ( !$user->getOption( $exceptionName ) ) {
				if ( $globalPrefs && array_key_exists( $preference, $globalPrefs ) ) {
					$toWarn[] = $preference;
				}
			}
		}
		if ( $toWarn ) {
			$toWarn = array_map( static function ( $str ) {
				return wfEscapeWikiText( "`$str`" );
			}, $toWarn );
			$apiModule->addWarning(
				[
					'apiwarn-globally-overridden',
					Message::listParam( $toWarn ),
					count( $toWarn ),
				],
				'globally-overridden'
			);
		}
	}

	/**
	 * Convenience method for getting a preferences factory instance and centralized
	 * wrestling with Phan.
	 *
	 * @return GlobalPreferencesFactory
	 */
	private static function getPreferencesFactory(): GlobalPreferencesFactory {
		/** @var GlobalPreferencesFactory $factory */
		$factory = MediaWikiServices::getInstance()->getPreferencesFactory();
		'@phan-var GlobalPreferencesFactory $factory';

		return $factory;
	}
}
