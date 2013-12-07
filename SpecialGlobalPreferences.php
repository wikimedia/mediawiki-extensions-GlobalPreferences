<?php

class SpecialGlobalPreferences extends SpecialPreferences {
	function __construct() {
		SpecialPage::__construct( 'GlobalPreferences' );
	}

	public function execute( $par ) {
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
		parent::execute( $par );
	}

	public function submitReset( $formData ) {
		// TODO: Should we have our own userright here?
		if ( !$this->getUser()->isAllowed( 'editmyoptions' ) ) {
			throw new PermissionsError( 'editmyoptions' );
		}

		GlobalPreferences::resetUserSettings( $this->getUser() );

		$url = $this->getTitle()->getFullURL( 'success' );

		$this->getOutput()->redirect( $url );

		return true;
	}

}