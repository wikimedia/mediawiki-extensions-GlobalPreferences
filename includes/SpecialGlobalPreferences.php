<?php

namespace GlobalPreferences;

use DerivativeContext;
use ErrorPageError;
use HTMLForm;
use IContextSource;
use MediaWiki\MediaWikiServices;
use PermissionsError;
use PreferencesForm;
use SpecialPage;
use SpecialPreferences;
use User;
use UserNotLoggedIn;

class SpecialGlobalPreferences extends SpecialPreferences {

	public function __construct() {
		SpecialPage::__construct( 'GlobalPreferences' );
	}

	/**
	 * Execute the special page.
	 * @param null|string $par The subpage name, if any.
	 * @throws ErrorPageError
	 * @throws UserNotLoggedIn
	 */
	public function execute( $par ) {
		// Dirty override to check user can set global prefs.
		if ( $this->getUser()->isAnon() ) {
			// @todo use our own error messages here
			$this->setHeaders();
			throw new UserNotLoggedIn();
		}
		/** @var GlobalPreferencesFactory $globalPreferencesFactory */
		$globalPreferencesFactory = MediaWikiServices::getInstance()->getPreferencesFactory();
		$globalPreferencesFactory->setUser( $this->getUser() );
		if ( !$globalPreferencesFactory->isUserGlobalized() ) {
			$this->setHeaders();
			throw new ErrorPageError( 'globalprefs-error-header', 'globalprefs-notglobal' );
		}

		// Add link back to (local) Preferences.
		$link = $this->getLinkRenderer()->makeKnownLink(
			static::getSafeTitleFor( 'Preferences' ),
			$this->msg( 'mypreferences' )->escaped()
		);
		// Same left-arrow as used in Skin::subPageSubtitle().
		$this->getOutput()->addSubtitle( "&lt; $link" );

		// Add module styles and scripts separately
		// so non-JS users get the styles quicker and to avoid a FOUC.
		$this->getOutput()->addModuleStyles( 'ext.GlobalPreferences.global-nojs' );
		$this->getOutput()->addModules( 'ext.GlobalPreferences.global' );
		parent::execute( $par );
	}

	/**
	 * Get the preferences form to use.
	 * @param User $user The user.
	 * @param IContextSource $context The context.
	 * @return PreferencesForm|HTMLForm
	 */
	protected function getFormObject( $user, IContextSource $context ) {
		$preferencesFactory = MediaWikiServices::getInstance()->getPreferencesFactory();
		$form = $preferencesFactory->getForm( $user, $context, GlobalPreferencesForm::class );
		return $form;
	}

	/**
	 * Display the preferences-reset confirmation page.
	 * This is identical to parent::showResetForm except with the message names changed.
	 * @throws PermissionsError
	 */
	protected function showResetForm() {
		if ( !$this->getUser()->isAllowed( 'editmyoptions' ) ) {
			throw new PermissionsError( 'editmyoptions' );
		}

		$this->getOutput()->addWikiMsg( 'globalprefs-reset-intro' );

		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $this->getPageTitle( 'reset' ) );
		$htmlForm = new HTMLForm( [], $context, 'globalprefs-restore' );

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
		if ( !$this->getUser()->isAllowed( 'editmyoptions' ) ) {
			throw new PermissionsError( 'editmyoptions' );
		}

		/** @var GlobalPreferencesFactory $preferencesFactory */
		$preferencesFactory = MediaWikiServices::getInstance()->getPreferencesFactory();
		$preferencesFactory->setUser( $this->getUser() );
		$preferencesFactory->resetGlobalUserSettings();

		$url = $this->getTitle()->getFullURL( 'success' );

		$this->getOutput()->redirect( $url );

		return true;
	}
}
