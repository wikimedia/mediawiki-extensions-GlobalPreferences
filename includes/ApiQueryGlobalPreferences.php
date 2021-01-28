<?php

namespace GlobalPreferences;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use ApiResult;

class ApiQueryGlobalPreferences extends ApiQueryBase {
	/**
	 * @var GlobalPreferencesFactory
	 */
	private $preferencesFactory;

	/**
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 * @param GlobalPreferencesFactory $factory
	 */
	public function __construct( ApiQuery $queryModule, $moduleName,
		GlobalPreferencesFactory $factory
	) {
		$this->preferencesFactory = $factory;
		parent::__construct( $queryModule, $moduleName, 'gpr' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( !$this->getUser()->isRegistered() ) {
			$this->dieWithError(
				[ 'apierror-mustbeloggedin', $this->msg( 'action-editmyoptions' ) ], 'notloggedin'
			);
		}

		if ( !$this->preferencesFactory->isUserGlobalized( $this->getUser() ) ) {
			$this->dieWithError( 'apierror-globalpreferences-notglobalized', 'notglobalized' );
		}

		$params = $this->extractRequestParams();
		$prop = array_flip( $params['prop'] );
		$result = [];
		ApiResult::setArrayType( $result, 'assoc' );

		if ( isset( $prop['preferences'] ) ) {
			$prefs = $this->preferencesFactory->getGlobalPreferencesValues( $this->getUser() );
			$result['preferences'] = $prefs;
			ApiResult::setArrayType( $result['preferences'], 'assoc' );
		}

		if ( isset( $prop['localoverrides'] ) ) {
			$overriddenPrefs = [];
			$userOptions = $this->getUser()->getOptions();
			foreach ( $userOptions as $pref => $value ) {
				if ( GlobalPreferencesFactory::isLocalPrefName( $pref ) ) {
					$mainPref = substr( $pref, 0,
						-strlen( GlobalPreferencesFactory::LOCAL_EXCEPTION_SUFFIX ) );
					if ( isset( $userOptions[$mainPref] ) ) {
						$overriddenPrefs[$mainPref] = $userOptions[$mainPref];
					}
				}
			}
			$result['localoverrides'] = $overriddenPrefs;
			ApiResult::setArrayType( $result['localoverrides'], 'assoc' );
		}

		$this->getResult()->addValue( 'query', $this->getModuleName(), $result );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'prop' => [
				ApiBase::PARAM_TYPE => [
					'preferences',
					'localoverrides',
				],
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_DFLT => 'preferences|localoverrides',
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getHelpUrls() {
		return [ 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:GlobalPreferences/API' ];
	}
}
