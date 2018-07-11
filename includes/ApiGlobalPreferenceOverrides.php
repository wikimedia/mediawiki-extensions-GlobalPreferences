<?php

namespace GlobalPreferences;

use ApiOptions;
use MediaWiki\MediaWikiServices;
use User;

class ApiGlobalPreferenceOverrides extends ApiOptions {
	private $prefs = [];
	private $resetPrefTypes = [];

	/**
	 * @inheritDoc
	 */
	public function execute() {
		/** @var GlobalPreferencesFactory $factory */
		$factory = MediaWikiServices::getInstance()->getPreferencesFactory();
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
	 * @inheritDoc
	 */
	protected function resetPreferences( array $kinds ) {
		if ( in_array( 'all', $kinds ) ) {
			$this->resetPrefTypes = User::listOptionKinds();
		} else {
			$this->resetPrefTypes = $kinds;
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function setPreference( $preference, $value ) {
		$exceptionName = $preference . GlobalPreferencesFactory::LOCAL_EXCEPTION_SUFFIX;
		if ( $value === null ) {
			$this->prefs[$exceptionName] = null;
		} else {
			$this->prefs[$preference] = $value;
			$this->prefs[$exceptionName] = 1;
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function commitChanges() {
		$user = $this->getUserForUpdates();
		if ( $this->resetPrefTypes ) {
			$prefs = $user->getOptions();
			$kinds = $this->getUserForUpdates()->getOptionKinds( $this->getContext(), $prefs );
			foreach ( $prefs as $pref => $value ) {
				$kind = $kinds[$pref];
				if ( in_array( $kind, $this->resetPrefTypes ) ) {
					$user->setOption( $pref . GlobalPreferencesFactory::LOCAL_EXCEPTION_SUFFIX, null );
				}
			}
		}
		foreach ( $this->prefs as $pref => $value ) {
			$user->setOption( $pref, $value );
		}
		$user->saveSettings();
	}

	/**
	 * @inheritDoc
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:GlobalPreferences/API';
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=globalpreferenceoverrides&reset=&token=123ABC'
				=> 'apihelp-globalpreferenceoverrides-example-reset',
			'action=globalpreferenceoverrides&change=skin=vector|hideminor=1&token=123ABC'
				=> 'apihelp-globalpreferenceoverrides-example-change',
		];
	}
}
