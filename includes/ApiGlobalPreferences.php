<?php

namespace GlobalPreferences;

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiOptionsBase;
use MediaWiki\User\Options\UserOptionsManager;

class ApiGlobalPreferences extends ApiOptionsBase {

	/** @var mixed[] */
	private $prefs = [];

	/** @var string[] */
	private $resetPrefTypes = [];

	/**
	 * @var GlobalPreferencesFactory
	 */
	private $factory;

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
		parent::__construct( $mainModule, $moduleName, $userOptionsManager, $factory );
		$this->factory = $factory;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$user = $this->getUserForUpdatesOrNull();
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
		$this->prefs[$preference] = $value;
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
			$kinds = $this->getFactory()->getResetKinds(
				$this->getUserForUpdates(),
				$this->getContext(),
				$prefs
			);
			foreach ( $prefs as $pref => $value ) {
				$kind = $kinds[$pref];
				if ( in_array( $kind, $this->resetPrefTypes ) ) {
					$prefs[$pref] = null;
				}
			}
		}
		$prefs = array_merge( $prefs, $this->prefs );
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
