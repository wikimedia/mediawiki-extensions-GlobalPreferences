<?php

namespace GlobalPreferences\Tests\Integration;

use GlobalPreferences\GlobalPreferencesServices;
use GlobalPreferences\Services\GlobalPreferencesConnectionProvider;
use GlobalPreferences\Services\GlobalPreferencesHookRunner;
use MediaWikiIntegrationTestCase;

/**
 * @covers \GlobalPreferences\GlobalPreferencesServices
 */
class GlobalPreferencesServicesTest extends MediaWikiIntegrationTestCase {
	/** @dataProvider provideGetters */
	public function testGetters( string $method, string $expectedClass ) {
		$this->assertInstanceOf(
			$expectedClass,
			GlobalPreferencesServices::wrap( $this->getServiceContainer() )->$method()
		);
	}

	public static function provideGetters() {
		return [
			'::getGlobalPreferencesConnectionProvider' => [
				'getGlobalPreferencesConnectionProvider', GlobalPreferencesConnectionProvider::class
			],
			'::getGlobalPreferencesHookRunner' => [
				'getGlobalPreferencesHookRunner', GlobalPreferencesHookRunner::class
			],
		];
	}
}
