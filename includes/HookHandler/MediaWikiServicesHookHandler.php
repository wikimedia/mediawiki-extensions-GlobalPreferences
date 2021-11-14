<?php

namespace GlobalPreferences\HookHandler;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

class MediaWikiServicesHookHandler implements MediaWikiServicesHook {

	/**
	 * Replace the PreferencesFactory service with the GlobalPreferencesFactory.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/MediaWikiServices
	 * @param MediaWikiServices $services The services object to use.
	 */
	public function onMediaWikiServices( $services ) {
		$services->redefineService( 'PreferencesFactory', static function ( MediaWikiServices $services ) {
			$mainConfig = $services->getMainConfig();
			$config = new ServiceOptions( GlobalPreferencesFactory::CONSTRUCTOR_OPTIONS,
				$mainConfig
			);
			$factory = new GlobalPreferencesFactory(
				$config,
				$services->getContentLanguage(),
				$services->getAuthManager(),
				$services->getLinkRendererFactory()->create(),
				$services->getNamespaceInfo(),
				$services->getPermissionManager(),
				$services->getLanguageConverterFactory()->getLanguageConverter(),
				$services->getLanguageNameUtils(),
				$services->getHookContainer(),
				$services->getUserOptionsLookup()
			);
			$factory->setLogger( LoggerFactory::getInstance( 'preferences' ) );
			$factory->setAutoGlobals( $mainConfig->get( 'GlobalPreferencesAutoPrefs' ) );

			return $factory;
		} );
	}

}
