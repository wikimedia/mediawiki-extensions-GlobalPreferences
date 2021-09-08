<?php

namespace GlobalPreferences;

use ApiMain;
use ApiOptions;
use MediaWiki\User\UserOptionsManager;

class ApiGlobalPreferences extends ApiOptions {
	private $prefs = [];
	private $resetPrefTypes = [];
	private $resetPrefs = [];

	/**
	 * @var GlobalPreferencesFactory
	 */
	private $factory;

	/**
	 * @var UserOptionsManager
	 */
	private $userOptionsManager;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param GlobalPreferencesFactory $factory
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		GlobalPreferencesFactory $factory,
		UserOptionsManager $userOptionsManager
	) {
		parent::__construct( $mainModule, $moduleName );
		$this->factory = $factory;
		$this->userOptionsManager = $userOptionsManager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$user = $this->getUserForUpdates();
		if ( $user ) {
			$factory = $this->getFactory();
			if ( !$factory->isUserGlobalized( $user ) ) {
				$this->dieWithError( 'apierror-globalpreferences-notglobalized', 'notglobalized' );
			}
		}
		parent::execute();
	}

	/**
	 * @return GlobalPreferencesFactory
	 */
	private function getFactory() {
		return $this->factory;
	}

	/**
	 * @inheritDoc
	 */
	protected function resetPreferences( array $kinds ) {
		if ( in_array( 'all', $kinds ) ) {
			$this->getFactory()->resetGlobalUserSettings( $this->getUserForUpdates() );
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
		$user = $this->getUserForUpdates();
		$prefs = $this->getFactory()->getGlobalPreferencesValues( $user, true );
		if ( $prefs === false ) {
			return;
		}
		if ( $this->resetPrefTypes ) {
			$kinds = $this->userOptionsManager->getOptionKinds(
				$this->getUserForUpdates(),
				$this->getContext(),
				$prefs
			);
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
		$factory->setGlobalPreferences( $user, $prefs, $this->getContext() );
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
