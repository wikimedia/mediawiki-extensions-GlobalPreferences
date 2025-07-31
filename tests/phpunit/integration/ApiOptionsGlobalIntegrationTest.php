<?php

namespace GlobalPreferences\Tests\Integration;

use GlobalPreferences\Storage;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;
use Wikimedia\TestingAccessWrapper;

/**
 * Test core ApiOptions when integrated with GlobalPreferences
 *
 * @group GlobalPreferences
 * @group Database
 * @covers \GlobalPreferences\GlobalUserOptionsStore
 * @covers \GlobalPreferences\ApiGlobalPreferenceOverrides
 */
class ApiOptionsGlobalIntegrationTest extends ApiTestCase {
	public function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'local',
			'GlobalPreferencesDB' => false,
		] );
	}

	public function testNotWritingToRealDatabase() {
		$storage = new Storage( 1 );
		$db = TestingAccessWrapper::newFromObject( $storage )->getDatabase( DB_PRIMARY );
		$this->assertSame( self::DB_PREFIX, $db->tablePrefix() );
	}

	private function doOptionsRequest( $params, Authority $performer ) {
		return $this->doApiRequestWithToken(
			$params + [ 'action' => 'options' ],
			null, $performer
		);
	}

	private function assertPrefsContain( $expected, Authority $performer ) {
		// Make sure the changed options are stored into the database
		$this->getServiceContainer()->getUserOptionsManager()
			->clearUserOptionsCache( $performer->getUser() );
		$res = $this->doOptionsRequest( [
			'action' => 'query',
			'meta' => 'userinfo',
			'uiprop' => 'options',
		], $performer );
		$this->assertArrayContains( $expected, $res[0]['query']['userinfo']['options'] );
	}

	public function testApiOptions() {
		// DefaultPreferencesFactory needs a context title for the signature preference
		$this->apiContext->setTitle( Title::makeTitle( NS_MAIN, 'ApiOptions' ) );

		$performer = $this->getTestUser()->getAuthority();
		// Set a local option
		$res = $this->doOptionsRequest( [ 'change' => 'gender=male' ], $performer );
		$this->assertSame( [ 'options' => 'success' ], $res[0] );

		// Override it with a non-default global option
		$res = $this->doOptionsRequest( [
			'action' => 'globalpreferences',
			'change' => 'gender=female',
		], $performer );
		$this->assertSame( 'success', $res[0]['globalpreferences'] );
		$this->assertPrefsContain( [ 'gender' => 'female' ], $performer );

		// Fail to set the local option
		$res = $this->doOptionsRequest( [
			'change' => 'gender=male',
			'errorformat' => 'plaintext'
		], $performer );
		$this->assertSame( 'global-option-ignored', $res[0]['warnings'][0]['code'] );

		// One option succeeds, one fails with a warning
		$res = $this->doOptionsRequest( [
			'change' => 'hideminor=1|gender=male',
			'errorformat' => 'plaintext'
		], $performer );
		$this->assertSame( 'success', $res[0]['options'] );
		$this->assertSame( 'global-option-ignored', $res[0]['warnings'][0]['code'] );

		// Check the current userinfo
		$this->assertPrefsContain( [ 'hideminor' => '1', 'gender' => 'female' ], $performer );

		// Update the global preference
		$res = $this->doOptionsRequest( [
			'change' => 'gender=male',
			'global' => 'update',
		], $performer );
		$this->assertSame( 'success', $res[0]['options'] );
		$this->assertPrefsContain( [ 'gender' => 'male' ], $performer );

		// Override the global preference with a non-default value
		$res = $this->doOptionsRequest( [
			'optionname' => 'gender',
			'optionvalue' => 'female',
			'global' => 'override',
		], $performer );
		$this->assertSame( 'success', $res[0]['options'] );
		$this->assertPrefsContain( [ 'gender' => 'female', 'gender-local-exception' => '1' ], $performer );

		// Override the global preference with the default
		$res = $this->doOptionsRequest( [
			'optionname' => 'gender',
			'optionvalue' => 'unknown',
			'global' => 'override',
		], $performer );
		$this->assertSame( 'success', $res[0]['options'] );
		$this->assertPrefsContain( [ 'gender' => 'unknown', 'gender-local-exception' => '1' ], $performer );

		// Override the global preference with action=globalpreferenceoverrides
		$res = $this->doOptionsRequest( [
			'action' => 'globalpreferenceoverrides',
			'optionname' => 'gender',
			'optionvalue' => 'male',
		], $performer );
		$this->assertSame( 'success', $res[0]['globalpreferenceoverrides'] );
		$this->assertPrefsContain( [ 'gender' => 'male', 'gender-local-exception' => '1' ], $performer );
	}

	public function testApiOptionsWhenGlobalSetToCreate() {
		// DefaultPreferencesFactory needs a context title for the signature preference
		$this->apiContext->setTitle( Title::makeTitle( NS_MAIN, 'ApiOptions' ) );

		$performer = $this->getTestUser()->getAuthority();
		// Set the 'hideminor' option globally so that the user has some global preferences set before the
		// code under test is called.
		$res = $this->doOptionsRequest( [
			'action' => 'globalpreferences',
			'change' => 'hideminor=1',
		], $performer );
		$this->assertSame( 'success', $res[0]['globalpreferences'] );
		$this->assertPrefsContain( [ 'hideminor' => '1' ], $performer );

		// Expect that the GlobalPreferencesSetGlobalPreferences hook is called but not the
		// LocalUserOptionsStoreSave hook, as the preference should have only been set globally.
		$this->setTemporaryHook( 'LocalUserOptionsStoreSave', function () {
			$this->fail( 'Did not expect the LocalUserOptionsStoreSave hook to be run.' );
		} );
		$globalPreferencesHookRun = false;
		$this->setTemporaryHook(
			'GlobalPreferencesSetGlobalPreferences',
			function ( $actualUser, $oldPreferences, $newPreferences ) use ( &$globalPreferencesHookRun, $performer ) {
				$globalPreferencesHookRun = true;

				$this->assertTrue( $performer->getUser()->equals( $actualUser ) );
				$this->assertSame( [ 'hideminor' => '1' ], $oldPreferences );
				$this->assertArrayEquals(
					[ 'gender' => 'male', 'hideminor' => '1' ],
					$newPreferences,
					false, true
				);
			}
		);

		$res = $this->doOptionsRequest( [
			'optionname' => 'gender',
			'optionvalue' => 'male',
			'global' => 'create',
		], $performer );
		$this->assertSame( 'success', $res[0]['options'] );
		$this->assertPrefsContain( [ 'gender' => 'male' ], $performer );
		$this->assertTrue( $globalPreferencesHookRun );
	}
}
