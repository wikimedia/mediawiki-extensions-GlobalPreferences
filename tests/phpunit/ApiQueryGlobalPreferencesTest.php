<?php

namespace GlobalPreferences\Test;

use GlobalPreferences\ApiQueryGlobalPreferences;
use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Api\ApiMain;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @group GlobalPreferences
 */
class ApiQueryGlobalPreferencesTest extends MediaWikiIntegrationTestCase {
	/**
	 * @param array $requestData
	 * @param array $globalPrefs
	 * @param array $localPrefs
	 * @return ApiQueryGlobalPreferences
	 */
	private function makeApi(
		array $requestData,
		array $globalPrefs,
		array $localPrefs
	) {
		$user = $this->createMock( User::class );
		$user->method( 'isNamed' )
			->willReturn( true );
		$request = new FauxRequest( $requestData );
		$context = new DerivativeContext( RequestContext::getMain() );
		/** @var User $user */
		$context->setUser( $user );
		$context->setRequest( $request );

		$main = new ApiMain( $context );
		$query = $main->getModuleManager()->getModule( 'query' );

		$factory = $this->getMockBuilder( GlobalPreferencesFactory::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'isUserGlobalized', 'getGlobalPreferencesValues' ] )
			->getMock();
		$factory->method( 'getGlobalPreferencesValues' )
			->willReturn( $globalPrefs );
		$factory->method( 'isUserGlobalized' )
			->willReturn( true );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOptions' )
			->willReturn( $localPrefs );

		/** @var GlobalPreferencesFactory $factory */
		return new ApiQueryGlobalPreferences(
			$query,
			'globalpreferences',
			$factory,
			$userOptionsLookup
		);
	}

	/**
	 * @covers \GlobalPreferences\ApiQueryGlobalPreferences::execute()
	 *
	 * @dataProvider provideApi
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

	public static function provideApi() {
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
