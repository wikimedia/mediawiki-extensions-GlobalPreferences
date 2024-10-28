<?php

namespace GlobalPreferences;

use GlobalPreferences\Services\GlobalPreferencesConnectionProvider;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;

// PHPUnit does not understand coverage for this file.
// It is covered though, see GlobalPreferencesServiceWiringTest.
// @codeCoverageIgnoreStart

return [
	'GlobalPreferences.GlobalPreferencesConnectionProvider' => static function (
		MediaWikiServices $services
	): GlobalPreferencesConnectionProvider {
		return new GlobalPreferencesConnectionProvider(
			new ServiceOptions(
				GlobalPreferencesConnectionProvider::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getConnectionProvider()
		);
	},
];

// @codeCoverageIgnoreEnd
