<?php

namespace GlobalPreferences\Tests\Integration\Services;

use GlobalPreferences\GlobalPreferencesServices;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \GlobalPreferences\Services\GlobalPreferencesConnectionProvider
 * @group Database
 */
class GlobalPreferencesConnectionProviderTest extends MediaWikiIntegrationTestCase {
	public function testGetReplicaDatabase() {
		$actualDbr = GlobalPreferencesServices::wrap( $this->getServiceContainer() )
			->getGlobalPreferencesConnectionProvider()
			->getReplicaDatabase();
		$this->assertInstanceOf( IReadableDatabase::class, $actualDbr );
		$this->assertSame(
			$this->getServiceContainer()->getDBLoadBalancerFactory()->getLocalDomainID(),
			$actualDbr->getDomainID()
		);
	}

	public function testGetPrimaryDatabase() {
		$actualDbw = GlobalPreferencesServices::wrap( $this->getServiceContainer() )
			->getGlobalPreferencesConnectionProvider()
			->getPrimaryDatabase();
		$this->assertInstanceOf( IDatabase::class, $actualDbw );
		$this->assertSame(
			$this->getServiceContainer()->getDBLoadBalancerFactory()->getLocalDomainID(),
			$actualDbw->getDomainID()
		);
	}

	/** @dataProvider provideGetDomainId */
	public function testGetDomainId( $globalPreferencesDBValue, $sharedDBValue, $expectedDomainId ) {
		$this->overrideConfigValues( [
			'GlobalPreferencesDB' => $globalPreferencesDBValue,
			'SharedDB' => $sharedDBValue,
		] );
		$objectUnderTest = GlobalPreferencesServices::wrap( $this->getServiceContainer() )
			->getGlobalPreferencesConnectionProvider();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertSame( $expectedDomainId, $objectUnderTest->getDomainId() );
	}

	public static function provideGetDomainId() {
		return [
			'No shared DB set' => [ false, false, false ],
			'No shared DB set but configs set to empty strings' => [ '', '', false ],
			'Shared DB set using wgSharedDB' => [ false, 'shared', 'shared' ],
			'Shared DB set using wgGlobalPreferencesDB' => [ 'globalpreferences', false, 'globalpreferences' ],
		];
	}
}
