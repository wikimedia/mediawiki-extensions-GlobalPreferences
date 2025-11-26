<?php

namespace GlobalPreferences;

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiOptionsBase;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;

class ApiGlobalPreferenceOverrides extends ApiOptionsBase {

	private GlobalPreferencesFactory $globalPrefs;

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
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
		$user = $this->getUser();
		if ( $user->isNamed() && !$this->globalPrefs->isUserGlobalized( $user ) ) {
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
				$this->getUser(), $this->getContext(), $kinds )
		);
		$this->getUserOptionsManager()->resetOptionsByName( $this->getUser(), $optionNames );
	}

	/**
	 * @inheritDoc
	 */
	protected function setPreference( $preference, $value ) {
		if ( $value === null ) {
			$this->getUserOptionsManager()->setOption(
				$this->getUser(),
				$preference . UserOptionsLookup::LOCAL_EXCEPTION_SUFFIX,
				null
			);
		} else {
			$this->getUserOptionsManager()->setOption(
				$this->getUser(), $preference, $value, UserOptionsManager::GLOBAL_OVERRIDE );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function commitChanges() {
		$this->getUserOptionsManager()->saveOptions( $this->getUser() );
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
