<?php

namespace GlobalPreferences\Test;

use ApiOptions;
use GlobalPreferences\GlobalPreferencesFactory;
use GlobalPreferences\Hooks;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiIntegrationTestCase;
use User;

/**
 * @group GlobalPreferences
 */
class HooksTest extends MediaWikiIntegrationTestCase {
	/**
	 * @covers \GlobalPreferences\Hooks::onApiOptions()
	 * @dataProvider provideOnApiOptions
	 * @param array $changes
	 * @param bool $errorExpected
	 */
	public function testOnApiOptions( array $changes, $errorExpected ) {
		$apiOptions = $this->getMockBuilder( ApiOptions::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'addWarning', 'getModuleName' ] )
			->getMock();
		$apiOptions->method( 'getModuleName' )
			->willReturn( 'options' );
		if ( !$errorExpected ) {
			$apiOptions->expects( self::never() )
				->method( 'addWarning' );
		} else {
			$apiOptions->expects( self::once() )
				->method( 'addWarning' );
		}

		$user = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->getMock();

		$userOptionsLookup = $this->getMockBuilder( UserOptionsLookup::class )
			->disableOriginalConstructor()
			->getMock();
		$userOptionsLookup->method( 'getOption' )
			->will( self::returnValueMap( [
				[ $user, 'skin', null, false, 0, 'monobook' ],
				[ $user, 'skin-local-exception', null, false, 0, true ],
			] ) );
		$this->setService( 'UserOptionsLookup', $userOptionsLookup );

		$factory = $this->getMockBuilder( GlobalPreferencesFactory::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getGlobalPreferencesValues' ] )
			->getMock();
		$factory->method( 'getGlobalPreferencesValues' )
			->willReturn( [
				'skin' => 'vector',
				'something' => 'happens',
			] );

		$this->setService( 'PreferencesFactory', $factory );
		/**
		 * @var ApiOptions $apiOptions
		 * @var User $user
		 */
		Hooks::onApiOptions( $apiOptions, $user, $changes );
	}

	public function provideOnApiOptions() {
		return [
			[ [ 'blah' => 'whatever' ], false ],
			[ [], false ],
			[ [ 'skin' => 'modern' ], false ],
			[ [ 'skin-local-exception' => null ], false ],
			[ [ 'something' => null ], true ],
		];
	}
}
