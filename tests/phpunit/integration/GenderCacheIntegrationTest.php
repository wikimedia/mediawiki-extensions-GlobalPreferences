<?php

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;

/**
 * Test GenderCache integrated with GlobalPreferences
 *
 * @group Database
 * @coversNothing
 */
class GenderCacheIntegrationTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'local',
			'GlobalPreferencesDB' => false,
		] );
	}

	public function testGenderCache() {
		$user = User::createNew( 'User' );
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$prefsFactory = $this->getServiceContainer()->getPreferencesFactory();
		if ( !( $prefsFactory instanceof GlobalPreferencesFactory ) ) {
			throw new \RuntimeException( 'GlobalPreferences is not installed' );
		}
		$context = RequestContext::getMain();
		$context->setTitle( Title::newMainPage() );

		// Test default
		$this->assertSame( 'unknown', $this->newGenderCache()->getGenderOf( $user ) );

		// Test local preference
		$userOptionsManager->setOption( $user, 'gender', 'male' );
		$userOptionsManager->saveOptions( $user );
		$this->assertSame( 'male', $this->newGenderCache()->getGenderOf( $user ) );

		// Test global preference overriding local preference
		$prefsFactory->setGlobalPreferences( $user, [ 'gender' => 'female' ], $context );
		$this->assertSame( 'female', $this->newGenderCache()->getGenderOf( $user ) );

		// Test local override
		$userOptionsManager->setOption( $user, 'gender', 'male',
			UserOptionsManager::GLOBAL_OVERRIDE );
		$userOptionsManager->saveOptions( $user );
		$this->assertSame( 'male', $this->newGenderCache()->getGenderOf( $user ) );

		// Test local override removal
		$userOptionsManager->setOption( $user, 'gender-local-exception', '' );
		$userOptionsManager->saveOptions( $user );
		$this->assertSame( 'female', $this->newGenderCache()->getGenderOf( $user ) );
	}

	private function newGenderCache() {
		$services = $this->getServiceContainer();
		$services->resetServiceForTesting( 'GenderCache' );
		return $services->getGenderCache();
	}
}
