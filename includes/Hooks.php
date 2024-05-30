<?php

namespace GlobalPreferences;

use ApiOptions;
use MediaWiki\Api\Hook\ApiOptionsHook;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Message\Message;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Preferences\Hook\PreferencesFormPreSaveHook;
use MediaWiki\Preferences\PreferencesFactory;
use MediaWiki\User\Options\Hook\SaveUserOptionsHook;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use Skin;

class Hooks implements
	BeforePageDisplayHook,
	SaveUserOptionsHook,
	PreferencesFormPreSaveHook,
	ApiOptionsHook
	{

	/** @var GlobalPreferencesFactory */
	private $preferencesFactory;

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/**
	 * @param PreferencesFactory $preferencesFactory
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		PreferencesFactory $preferencesFactory,
		UserOptionsManager $userOptionsManager
	) {
		$this->preferencesFactory = $preferencesFactory;
		$this->userOptionsManager = $userOptionsManager;
	}

	/**
	 * Allows last minute changes to the output page, e.g. adding of CSS or JavaScript by extensions.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * @param OutputPage $out The output page.
	 * @param Skin $skin The skin. Not used.
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $out->getTitle()->isSpecial( 'Preferences' ) ) {
			// Add module styles and scripts separately
			// so non-JS users get the styles quicker and to avoid a FOUC.
			$out->addModuleStyles( 'ext.GlobalPreferences.local-nojs' );
			$out->addModules( 'ext.GlobalPreferences.local' );
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
	public function onSaveUserOptions( UserIdentity $user, array &$modifiedOptions, array $originalOptions ) {
		if ( $this->preferencesFactory->onGlobalPrefsPage() ) {
			// It shouldn't be possible to save local options here,
			// but never save on this page anyways.
			return false;
		}

		$this->preferencesFactory->handleLocalPreferencesChange( $user, $modifiedOptions, $originalOptions );

		return true;
	}

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/PreferencesFormPreSave
	 * @param array $formData An associative array containing the data from the preferences form.
	 * @param HTMLForm $form The HTMLForm object that represents the preferences form.
	 * @param User $user The User object that can be used to change the user's preferences.
	 * @param bool &$result The boolean return value of the Preferences::tryFormSubmit method.
	 * @param array $oldUserOptions Array with user's old options (before save)
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onPreferencesFormPreSave( $formData, $form, $user, &$result, $oldUserOptions ) {
		if ( !$this->preferencesFactory->onGlobalPrefsPage( $form ) ) {
			return $this->localPreferencesFormPreSave( $formData, $user );
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
	private function localPreferencesFormPreSave( array $formData, User $user ): bool {
		foreach ( $formData as $pref => $value ) {
			if ( !GlobalPreferencesFactory::isLocalPrefName( $pref ) ) {
				continue;
			}
			// Determine the real name of the preference.
			$suffixLen = strlen( UserOptionsLookup::LOCAL_EXCEPTION_SUFFIX );
			$realName = substr( $pref, 0, -$suffixLen );
			if ( isset( $formData[$realName] ) ) {
				// Not a CheckMatrix field
				continue;
			}
			$checkMatrix = preg_grep( "/^$realName-/", array_keys( $formData ) );
			foreach ( $checkMatrix as $check ) {
				$localExceptionName = $check . UserOptionsLookup::LOCAL_EXCEPTION_SUFFIX;
				$this->userOptionsManager->setOption( $user, $localExceptionName, $value );
			}
		}
		return true;
	}

	/**
	 * @param ApiOptions $apiModule Calling ApiOptions object
	 * @param User $user User object whose preferences are being changed
	 * @param array $changes Associative array of preference name => value
	 * @param string[] $resetKinds Array of strings specifying which options kinds to reset
	 *   See PreferencesFactory::listResetKinds() for possible values.
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onApiOptions( $apiModule, $user, $changes, $resetKinds ) {
		$globalPrefs = $this->preferencesFactory->getGlobalPreferencesValues( $user );

		$toWarn = [];
		foreach ( array_keys( $changes ) as $preference ) {
			if ( GlobalPreferencesFactory::isLocalPrefName( $preference ) ) {
				continue;
			}
			$exceptionName = $preference . GlobalPreferencesFactory::LOCAL_EXCEPTION_SUFFIX;
			if ( !$this->userOptionsManager->getOption( $user, $exceptionName ) ) {
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
}
