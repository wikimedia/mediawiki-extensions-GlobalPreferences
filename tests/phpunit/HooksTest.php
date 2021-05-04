<?php

namespace GlobalPreferences\Test;

use ApiOptions;
use GlobalPreferences\GlobalPreferencesFactory;
use GlobalPreferences\Hooks;
use MediaWikiTestCase;
use User;

/**
 * @group GlobalPreferences
 */
class HooksTest extends MediaWikiTestCase {
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
			->onlyMethods( [ 'getOption' ] )
			->getMock();
		$user->method( 'getOption' )
			->will( self::returnValueMap( [
				[ 'skin', null, false, 'monobook' ],
				[ 'skin-local-exception', null, false, true ],
			] ) );

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
