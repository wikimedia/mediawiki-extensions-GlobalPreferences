<?php

namespace GlobalPreferences;

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiOptionsBase;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;

class ApiGlobalPreferenceOverrides extends ApiOptionsBase {

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
		$optionNames = array_map(
			static fn ( $name ) => $name . UserOptionsLookup::LOCAL_EXCEPTION_SUFFIX,
			$this->globalPrefs->getOptionNamesForReset(
				$this->getUserForUpdates(), $this->getContext(), $kinds )
		);
		$this->getUserOptionsManager()->resetOptionsByName( $this->getUserForUpdates(), $optionNames );
	}

	/**
	 * @inheritDoc
	 */
	protected function setPreference( $preference, $value ) {
		if ( $value === null ) {
			$this->getUserOptionsManager()->setOption(
				$this->getUserForUpdates(),
				$preference . UserOptionsLookup::LOCAL_EXCEPTION_SUFFIX,
				null
			);
		} else {
			$this->getUserOptionsManager()->setOption(
				$this->getUserForUpdates(), $preference, $value, UserOptionsManager::GLOBAL_OVERRIDE );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function commitChanges() {
		$this->getUserOptionsManager()->saveOptions( $this->getUserForUpdates() );
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
