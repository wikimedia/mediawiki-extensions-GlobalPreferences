<?php

namespace GlobalPreferences\Test;

use ApiMain;
use ApiQuery;
use DerivativeContext;
use FauxRequest;
use GlobalPreferences\ApiQueryGlobalPreferences;
use GlobalPreferences\GlobalPreferencesFactory;
use MediaWikiTestCase;
use RequestContext;
use User;

/**
 * @group GlobalPreferences
 */
class ApiQueryGlobalPreferencesTest extends MediaWikiTestCase {
	/**
	 * @param array $requestData
	 * @param array $globalPrefs
	 * @param array $localPrefs
	 * @return ApiQueryGlobalPreferences
	 */
	private function makeApi( array $requestData,
		array $globalPrefs,
		array $localPrefs
	) {
		$user = $this->getMockBuilder( User::class )
			->setMethods( [ 'getOptions', 'isAnon' ] )
			->getMock();
		$user->method( 'getOptions' )
			->willReturn( $localPrefs );
		$user->method( 'isAnon' )
			->willReturn( false );
		$request = new FauxRequest( $requestData );
		$context = new DerivativeContext( RequestContext::getMain() );
		/** @var User $user */
		$context->setUser( $user );
		$context->setRequest( $request );

		$main = new ApiMain( $context );
		$query = new ApiQuery( $main, 'query' );

		$factory = $this->getMockBuilder( GlobalPreferencesFactory::class )
			->disableOriginalConstructor()
			->setMethods( [ 'isUserGlobalized', 'getGlobalPreferencesValues' ] )
			->getMock();
		$factory->method( 'getGlobalPreferencesValues' )
			->willReturn( $globalPrefs );
		$factory->method( 'isUserGlobalized' )
			->willReturn( true );

		/** @var GlobalPreferencesFactory $factory */
		return new ApiQueryGlobalPreferences( $query, 'globalpreferences', $factory );
	}

	/**
	 * @covers \GlobalPreferences\ApiQueryGlobalPreferences::execute()
	 *
	 * @dataProvider provideApi
	 * @param array $requestData
	 * @param array $globalPrefs
	 * @param array $localPrefs
	 * @param array $expected
	 */
	public function testApi( array $requestData,
		array $globalPrefs,
		array $localPrefs,
		array $expected
	) {
		$requestData['action'] = 'query';
		$requestData['meta'] = 'globalpreferences';
		/** @var ApiQueryGlobalPreferences $api */
		$api = $this->makeApi( $requestData, $globalPrefs, $localPrefs );
		$api->execute();
		$result = $api->getResult()->getResultData()['query']['globalpreferences'];
		self::assertEquals( $expected, self::filterApiResult( $result ) );
	}

	public function provideApi() {
		return [
			[
				[],
				[],
				[],
				[
					'preferences' => [],
					'localoverrides' => [],
				],
			],
			[
				[ 'gprprop' => '' ],
				[ 'skin' => 'vector' ],
				[ 'skin' => 'monobook' ],
				[],
			],
			[
				[],
				[ 'skin' => 'vector' ],
				[ 'skin' => 'monobook', 'skin-local-exception' => 1 ],
				[
					'preferences' => [ 'skin' => 'vector' ],
					'localoverrides' => [ 'skin' => 'monobook' ],
				],
			],
			[
				[ 'gprprop' => 'preferences|localoverrides' ],
				[ 'skin' => 'vector' ],
				[ 'skin' => 'monobook', 'skin-local-exception' => 1 ],
				[
					'preferences' => [ 'skin' => 'vector' ],
					'localoverrides' => [ 'skin' => 'monobook' ],
				],
			],
			[
				[ 'gprprop' => 'preferences' ],
				[ 'skin' => 'vector' ],
				[ 'skin' => 'monobook', 'skin-local-exception' => 1 ],
				[
					'preferences' => [ 'skin' => 'vector' ],
				],
			],
			[
				[ 'gprprop' => 'localoverrides' ],
				[ 'skin' => 'vector' ],
				[ 'skin' => 'monobook', 'skin-local-exception' => 1 ],
				[
					'localoverrides' => [ 'skin' => 'monobook' ],
				],
			],
		];
	}

	/**
	 * The internal representation of API results have metadata keys that we don't want to care about
	 *
	 * @param array $data
	 * @return array
	 */
	private static function filterApiResult( array $data ) {
		$result = [];
		foreach ( $data as $key => $value ) {
			if ( $key[0] === '_' ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$value = self::filterApiResult( $value );
			}
			$result[$key] = $value;
		}

		return $result;
	}
}
