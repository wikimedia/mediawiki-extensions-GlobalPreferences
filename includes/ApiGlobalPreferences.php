<?php

namespace GlobalPreferences;

use ApiOptions;
use MediaWiki\MediaWikiServices;

class ApiGlobalPreferences extends ApiOptions {
	private $prefs = [];
	private $resetPrefTypes = [];
	private $resetPrefs = [];

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$factory = $this->getFactory();
		$user = $this->getUserForUpdates();
		if ( $user ) {
			$factory->setUser( $user );
			if ( !$factory->isUserGlobalized() ) {
				$this->dieWithError( 'apierror-globalpreferences-notglobalized', 'notglobalized' );
			}
		}
		parent::execute();
	}

	/**
	 * @return GlobalPreferencesFactory
	 */
	private function getFactory() {
		/** @var GlobalPreferencesFactory $factory */
		$factory = MediaWikiServices::getInstance()->getPreferencesFactory();
		$factory->setUser( $this->getUserForUpdates() );
		return $factory;
	}

	/**
	 * @inheritDoc
	 */
	protected function resetPreferences( array $kinds ) {
		if ( in_array( 'all', $kinds ) ) {
			$this->getFactory()->resetGlobalUserSettings();
		} else {
			$this->resetPrefTypes = $kinds;
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function setPreference( $preference, $value ) {
		if ( $value === null ) {
			$this->resetPrefs[] = $preference;
		} else {
			$this->prefs[$preference] = $value;
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function commitChanges() {
		$factory = $this->getFactory();
		$prefs = $this->getFactory()->getGlobalPreferencesValues( true );
		if ( $this->resetPrefTypes ) {
			$kinds = $this->getUserForUpdates()->getOptionKinds( $this->getContext(), $prefs );
			foreach ( $prefs as $pref => $value ) {
				$kind = $kinds[$pref];
				if ( in_array( $kind, $this->resetPrefTypes ) ) {
					unset( $prefs[$pref] );
				}
			}
		}
		$prefs = array_merge( $prefs, $this->prefs );
		foreach ( $this->resetPrefs as $pref ) {
			unset( $prefs[$pref] );
		}
		$factory->setGlobalPreferences( $prefs, $this->getContext() );
	}

	/**
	 * @inheritDoc
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Globalpreferences';
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=globalpreferences&change=skin=&token=123ABC'
				=> 'apihelp-globalpreferences-example-reset-one',
			'action=globalpreferences&reset=&token=123ABC'
				=> 'apihelp-globalpreferences-example-reset',
			'action=globalpreferences&change=skin=vector|hideminor=1&token=123ABC'
				=> 'apihelp-globalpreferences-example-change',
		];
	}
}
