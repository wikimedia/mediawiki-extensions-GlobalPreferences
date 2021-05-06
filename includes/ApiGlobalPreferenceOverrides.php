<?php

namespace GlobalPreferences;

use ApiMain;
use ApiOptions;
use MediaWiki\User\UserOptionsManager;

class ApiGlobalPreferenceOverrides extends ApiOptions {
	private $prefs = [];
	private $resetPrefTypes = [];

	/**
	 * @var GlobalPreferencesFactory
	 */
	private $preferencesFactory;

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
		$this->preferencesFactory = $factory;
		$this->userOptionsManager = $userOptionsManager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$user = $this->getUserForUpdates();
		if ( $user && !$this->preferencesFactory->isUserGlobalized( $user ) ) {
			$this->dieWithError( 'apierror-globalpreferences-notglobalized', 'notglobalized' );
		}
		parent::execute();
	}

	/**
	 * @inheritDoc
	 */
	protected function resetPreferences( array $kinds ) {
		if ( in_array( 'all', $kinds ) ) {
			$this->resetPrefTypes = $this->userOptionsManager->listOptionKinds();
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
		$user = $this->getUser();
		if ( $this->resetPrefTypes ) {
			$prefs = $this->userOptionsManager->getOptions( $user, UserOptionsManager::READ_EXCLUSIVE );
			$kinds = $this->userOptionsManager->getOptionKinds(
				$user,
				$this->getContext(),
				$prefs
			);
			foreach ( $prefs as $pref => $value ) {
				$kind = $kinds[$pref];
				if ( in_array( $kind, $this->resetPrefTypes ) ) {
					$this->userOptionsManager->setOption(
						$user,
						$pref . GlobalPreferencesFactory::LOCAL_EXCEPTION_SUFFIX,
						null
					);
				}
			}
		}
		foreach ( $this->prefs as $pref => $value ) {
			$this->userOptionsManager->setOption( $user, $pref, $value );
		}
		$this->userOptionsManager->saveOptions( $user );
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
