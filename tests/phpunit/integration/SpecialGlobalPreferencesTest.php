<?php

use GlobalPreferences\SpecialGlobalPreferences;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;

/**
 * @covers \GlobalPreferences\SpecialGlobalPreferences
 * @group Database
 */
class SpecialGlobalPreferencesTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		$this->overrideMwServices( null, [
			'CentralIdLookup' => function () {
				return $this->getMockCentralLookup();
			}
		] );
		parent::setUp();
	}

	private function getMockCentralLookup() {
		$lookup = $this->createMock( CentralIdLookup::class );
		$lookup->method( 'isAttached' )
			->willReturnCallback( static function ( $user ) {
				return $user->getName() === 'Global';
			} );
		$lookup->method( 'isOwned' )
			->willReturnCallback( static function ( $user ) {
				return $user->getName() === 'Global';
			} );
		$lookup->method( 'centralIdFromName' )
			->willReturnCallback( static function ( $name ) {
				return $name === 'Global' ? 1 : 0;
			} );
		$lookup->method( 'lookupOwnedUserNames' )
			->willReturnCallback( static function ( $nameToId ) {
				foreach ( $nameToId as $name => &$id ) {
					if ( $name === 'Global' ) {
						$id = 1;
					}
				}
				return $nameToId;
			} );
		return $lookup;
	}

	private function createUser( $name ) {
		return User::createNew( $name );
	}

	private function getAnonUser() {
		return new UserIdentityValue( 0, '127.0.0.1' );
	}

	private function newSpecialPage() {
		$services = $this->getServiceContainer();
		return new SpecialGlobalPreferences(
			$services->getPermissionManager(),
			$services->getPreferencesFactory()
		);
	}

	private function executeAndGetOutput( $subPage, $user ) {
		// I considered using SpecialPageTestBase but it throws away the OutputPage
		$specialPage = $this->newSpecialPage();
		( new SpecialPageExecutor() )->executeSpecialPage(
			$specialPage,
			$subPage,
			null,
			'qqx',
			new SimpleAuthority( $user, [] )
		);
		return $specialPage->getOutput();
	}

	public function testSubpageRedirect() {
		$out = $this->executeAndGetOutput(
			'subpage',
			$this->getAnonUser()
		);
		$this->assertNotEmpty( $out->getRedirect() );
	}

	public function testNotLoggedIn() {
		$this->expectException( UserNotLoggedIn::class );
		$this->executeAndGetOutput(
			null,
			$this->getAnonUser()
		);
	}

	public function testNotGlobalized() {
		$this->expectException( ErrorPageError::class );
		$this->executeAndGetOutput(
			null,
			$this->createUser( 'NonGlobal' )
		);
	}

	public function testExecute() {
		$out = $this->executeAndGetOutput(
			null,
			$this->createUser( 'Global' )
		);
		$this->assertStringContainsString( '<a', $out->getSubtitle() );
		$this->assertContains( 'ext.GlobalPreferences.global', $out->getModules() );
	}

}
