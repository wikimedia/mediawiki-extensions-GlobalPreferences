<?php

namespace GlobalPreferences\Test;

use GlobalPreferences\Storage;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
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
	/** @var User */
	private $user;

	public function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'local',
			'GlobalPreferencesDB' => false,
		] );

		$this->user = $this->getTestUser()->getUser();
	}

	public function testNotWritingToRealDatabase() {
		$storage = new Storage( 1 );
		$db = TestingAccessWrapper::newFromObject( $storage )->getDatabase( DB_PRIMARY );
		$this->assertSame( self::DB_PREFIX, $db->tablePrefix() );
	}

	private function doOptionsRequest( $params ) {
		return $this->doApiRequestWithToken(
			$params + [ 'action' => 'options' ],
			null, $this->user
		);
	}

	private function assertPrefsContain( $expected ) {
		// Make sure the changed options are stored into the database
		$this->getServiceContainer()->getUserOptionsManager()
			->clearUserOptionsCache( $this->user );
		$res = $this->doOptionsRequest( [
			'action' => 'query',
			'meta' => 'userinfo',
			'uiprop' => 'options',
		] );
		$this->assertArrayContains( $expected, $res[0]['query']['userinfo']['options'] );
	}

	public function testApiOptions() {
		// DefaultPreferencesFactory needs a context title for the signature preference
		$this->apiContext->setTitle( Title::makeTitle( NS_MAIN, 'ApiOptions' ) );

		// Set a local option
		$res = $this->doOptionsRequest( [ 'change' => 'gender=male' ] );
		$this->assertSame( [ 'options' => 'success' ], $res[0] );

		// Override it with a non-default global option
		$res = $this->doOptionsRequest( [
			'action' => 'globalpreferences',
			'change' => 'gender=female',
		] );
		$this->assertSame( 'success', $res[0]['globalpreferences'] );
		$this->assertPrefsContain( [ 'gender' => 'female' ] );

		// Fail to set the local option
		$res = $this->doOptionsRequest( [
			'change' => 'gender=male',
			'errorformat' => 'plaintext'
		] );
		$this->assertSame( 'global-option-ignored', $res[0]['warnings'][0]['code'] );

		// One option succeeds, one fails with a warning
		$res = $this->doOptionsRequest( [
			'change' => 'hideminor=1|gender=male',
			'errorformat' => 'plaintext'
		] );
		$this->assertSame( 'success', $res[0]['options'] );
		$this->assertSame( 'global-option-ignored', $res[0]['warnings'][0]['code'] );

		// Check the current userinfo
		$this->assertPrefsContain( [ 'hideminor' => '1', 'gender' => 'female' ] );

		// Update the global preference
		$res = $this->doOptionsRequest( [
			'change' => 'gender=male',
			'global' => 'update',
		] );
		$this->assertSame( 'success', $res[0]['options'] );
		$this->assertPrefsContain( [ 'gender' => 'male' ] );

		// Override the global preference with a non-default value
		$res = $this->doOptionsRequest( [
			'optionname' => 'gender',
			'optionvalue' => 'female',
			'global' => 'override',
		] );
		$this->assertSame( 'success', $res[0]['options'] );
		$this->assertPrefsContain( [ 'gender' => 'female', 'gender-local-exception' => '1' ] );

		// Override the global preference with the default
		$res = $this->doOptionsRequest( [
			'optionname' => 'gender',
			'optionvalue' => 'unknown',
			'global' => 'override',
		] );
		$this->assertSame( 'success', $res[0]['options'] );
		$this->assertPrefsContain( [ 'gender' => 'unknown', 'gender-local-exception' => '1' ] );

		// Override the global preference with action=globalpreferenceoverrides
		$res = $this->doOptionsRequest( [
			'action' => 'globalpreferenceoverrides',
			'optionname' => 'gender',
			'optionvalue' => 'male',
		] );
		$this->assertSame( 'success', $res[0]['globalpreferenceoverrides'] );
		$this->assertPrefsContain( [ 'gender' => 'male', 'gender-local-exception' => '1' ] );
	}
}
