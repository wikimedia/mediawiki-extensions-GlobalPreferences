<?php

namespace GlobalPreferences;

use DerivativeContext;
use ErrorPageError;
use HTMLForm;
use PermissionsError;
use SpecialPage;
use SpecialPreferences;
use UserNotLoggedIn;

class SpecialGlobalPreferences extends SpecialPreferences {
	function __construct() {
		SpecialPage::__construct( 'GlobalPreferences' );
	}

	/**
	 * Execute the special page.
	 * @param null|string $par The subpage name, if any.
	 * @throws ErrorPageError
	 * @throws UserNotLoggedIn
	 */
	public function execute( $par ) {
		// Because parent::showResetForm() is private, we have to override it separately here.
		if ( $par == 'reset' ) {
			$this->showGlobalPrefsResetForm();
			return;
		}

		// Dirty override to check user can set global prefs.
		if ( $this->getUser()->isAnon() ) {
			// @todo use our own error messages here
			$this->setHeaders();
			throw new UserNotLoggedIn();
		}
		if ( !GlobalPreferences::isUserGlobalized( $this->getUser() ) ) {
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
		$this->getOutput()->addModuleStyles( 'ext.GlobalPreferences.special.nojs' );
		$this->getOutput()->addModules( 'ext.GlobalPreferences.special' );
		parent::execute( $par );
	}

	/**
	 * Display the preferences-reset confirmation page.
	 * This mostly repeats code in parent::execute() and parent::showResetForm().
	 * @throws PermissionsError
	 */
	protected function showGlobalPrefsResetForm() {
		// TODO: Should we have our own userright here?
		if ( !$this->getUser()->isAllowed( 'editmyoptions' ) ) {
			throw new PermissionsError( 'editmyoptions' );
		}

		// This section is duplicated from parent::execute().
		$this->setHeaders();
		$this->outputHeader();
		$out = $this->getOutput();
		// Prevent hijacked user scripts from sniffing passwords etc.
		$out->disallowUserJs();
		// Use the same message as normal Preferences for the login-redirection message.
		$this->requireLogin( 'prefsnologintext2' );
		$this->checkReadOnly();

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
	 * Handle reset submission (subpage '/reset').
	 * @param string[] $formData The submitted data (not used).
	 * @return bool
	 * @throws PermissionsError
	 */
	public function submitReset( $formData ) {
		// TODO: Should we have our own userright here?
		if ( !$this->getUser()->isAllowed( 'editmyoptions' ) ) {
			throw new PermissionsError( 'editmyoptions' );
		}

		GlobalPreferences::resetGlobalUserSettings( $this->getUser() );

		$url = $this->getTitle()->getFullURL( 'success' );

		$this->getOutput()->redirect( $url );

		return true;
	}

}
