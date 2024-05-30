<?php

namespace GlobalPreferences;

use ApiMain;
use ApiOptionsBase;
use IDBAccessObject;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserOptionsLookup;

class ApiGlobalPreferenceOverrides extends ApiOptionsBase {

	/** @var mixed[] */
	private $prefs = [];

	/** @var string[] */
	private $resetPrefTypes = [];

	/**
	 * @var GlobalPreferencesFactory
	 */
	private $globalPrefs;

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
		$this->globalPrefs = $factory;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$user = $this->getUserForUpdatesOrNull();
		if ( $user && !$this->globalPrefs->isUserGlobalized( $user ) ) {
			$this->dieWithError( 'apierror-globalpreferences-notglobalized', 'notglobalized' );
		}
		parent::execute();
	}

	/**
	 * @inheritDoc
	 */
	protected function resetPreferences( array $kinds ) {
		if ( in_array( 'all', $kinds ) ) {
			$this->resetPrefTypes = $this->globalPrefs->listResetKinds();
		} else {
			$this->resetPrefTypes = $kinds;
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function setPreference( $preference, $value ) {
		$exceptionName = $preference . UserOptionsLookup::LOCAL_EXCEPTION_SUFFIX;
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
			$prefs = $this->getUserOptionsManager()->getOptions( $user, IDBAccessObject::READ_EXCLUSIVE );
			$kinds = $this->globalPrefs->getResetKinds(
				$user,
				$this->getContext(),
				$prefs
			);
			foreach ( $prefs as $pref => $value ) {
				$kind = $kinds[$pref];
				if ( in_array( $kind, $this->resetPrefTypes ) ) {
					$this->getUserOptionsManager()->setOption(
						$user,
						$pref . UserOptionsLookup::LOCAL_EXCEPTION_SUFFIX,
						null
					);
				}
			}
		}
		foreach ( $this->prefs as $pref => $value ) {
			$this->getUserOptionsManager()->setOption( $user, $pref, $value );
		}
		$this->getUserOptionsManager()->saveOptions( $user );
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
