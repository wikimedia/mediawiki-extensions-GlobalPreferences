<?php

namespace GlobalPreferences;

use DerivativeContext;
use ErrorPageError;
use HTMLForm;
use IContextSource;
use MediaWiki\MediaWikiServices;
use PermissionsError;
use PreferencesFormOOUI;
use SpecialPreferences;
use User;
use UserNotLoggedIn;

class SpecialGlobalPreferences extends SpecialPreferences {

	public function __construct() {
		parent::__construct();
		$this->mName = 'GlobalPreferences';
	}

	/**
	 * Execute the special page.
	 * @param null|string $par The subpage name, if any.
	 * @throws ErrorPageError
	 * @throws UserNotLoggedIn
	 */
	public function execute( $par ) {
		// Call the parent
		parent::execute( $par );

		// Remove subpages other than 'reset', including trailing slash.
		if ( $par !== null && $par !== 'reset' ) {
			$this->getOutput()->redirect( rtrim( $this->getPageTitle()->getCanonicalURL(), '/' ) );
			return;
		}
		// Dirty override to check user can set global prefs.
		if ( !$this->getUser()->isRegistered() ) {
			// @todo use our own error messages here
			$this->setHeaders();
			throw new UserNotLoggedIn();
		}
		/** @var GlobalPreferencesFactory $globalPreferencesFactory */
		$globalPreferencesFactory = MediaWikiServices::getInstance()->getPreferencesFactory();
		'@phan-var GlobalPreferencesFactory $globalPreferencesFactory';
		if ( !$globalPreferencesFactory->isUserGlobalized( $this->getUser() ) ) {
			$this->setHeaders();
			throw new ErrorPageError( 'globalprefs-error-header', 'globalprefs-notglobal' );
		}

		// Add link back to (local) Preferences.
		if ( $par === null ) {
			$link = $this->getLinkRenderer()->makeKnownLink(
				static::getSafeTitleFor( 'Preferences' ),
				$this->msg( 'mypreferences' )->text()
			);
			// Same left-arrow as used in Skin::subPageSubtitle().
			$this->getOutput()->addSubtitle( "&lt; $link" );
		}

		// Add module styles and scripts separately
		// so non-JS users get the styles quicker and to avoid a FOUC.
		$this->getOutput()->addModuleStyles( 'ext.GlobalPreferences.global-nojs' );
		$this->getOutput()->addModules( 'ext.GlobalPreferences.global' );
	}

	/**
	 * Get the preferences form to use.
	 * @param User $user
	 * @param IContextSource $context
	 * @return PreferencesFormOOUI|HTMLForm
	 */
	protected function getFormObject( $user, IContextSource $context ) {
		$preferencesFactory = MediaWikiServices::getInstance()->getPreferencesFactory();
		$form = $preferencesFactory->getForm( $user, $context, GlobalPreferencesFormOOUI::class );
		return $form;
	}

	/**
	 * Display the preferences-reset confirmation page.
	 * This is identical to parent::showResetForm except with the message names changed.
	 * @throws PermissionsError
	 */
	protected function showResetForm() {
		if ( !MediaWikiServices::getInstance()->getPermissionManager()
				->userHasRight( $this->getUser(), 'editmyoptions' )
		) {
			throw new PermissionsError( 'editmyoptions' );
		}

		$this->getOutput()->addWikiMsg( 'globalprefs-reset-intro' );

		$context = new DerivativeContext( $this->getContext() );
		// Reset subpage
		$context->setTitle( $this->getPageTitle( 'reset' ) );
		$htmlForm = HTMLForm::factory( 'ooui', [], $context, 'globalprefs-restore' );

		$htmlForm->setSubmitTextMsg( 'globalprefs-restoreprefs' );
		$htmlForm->setSubmitDestructive();
		$htmlForm->setSubmitCallback( [ $this, 'submitReset' ] );
		$htmlForm->suppressReset();

		$htmlForm->show();
	}

	/**
	 * Adds help link with an icon via page indicators.
	 * @param string $to Ignored.
	 * @param bool $overrideBaseUrl Whether $url is a full URL, to avoid MW.o.
	 */
	public function addHelpLink( $to, $overrideBaseUrl = false ) {
		parent::addHelpLink( 'Help:Extension:GlobalPreferences', $overrideBaseUrl );
	}

	/**
	 * Handle reset submission (subpage '/reset').
	 * @param string[] $formData The submitted data (not used).
	 * @return bool
	 * @throws PermissionsError
	 */
	public function submitReset( $formData ) {
		if ( !MediaWikiServices::getInstance()->getPermissionManager()
				->userHasRight( $this->getUser(), 'editmyoptions' )
		) {
			throw new PermissionsError( 'editmyoptions' );
		}

		/** @var GlobalPreferencesFactory $preferencesFactory */
		$preferencesFactory = MediaWikiServices::getInstance()->getPreferencesFactory();
		'@phan-var GlobalPreferencesFactory $preferencesFactory';
		$preferencesFactory->resetGlobalUserSettings( $this->getUser() );

		$url = $this->getPageTitle()->getFullURL( 'success' );

		$this->getOutput()->redirect( $url );

		return true;
	}
}
