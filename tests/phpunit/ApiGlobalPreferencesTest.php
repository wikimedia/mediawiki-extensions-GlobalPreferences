<?php

namespace phpunit;

use Generator;
use GlobalPreferences\Storage;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * @group GlobalPreferences
 * @group API
 * @group Database
 * @covers \GlobalPreferences\ApiGlobalPreferences
 */
class ApiGlobalPreferencesTest extends ApiTestCase {

	private User $user;

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'local',
			'GlobalPreferencesDB' => false,
		] );

		$this->user = $this->getTestUser()->getUser();
	}

	/**
	 * @dataProvider provideExecute
	 */
	public function testExecute( array $params, array $initialPrefs, array $expectedPrefs ): void {
		$params = array_merge( [
			'action' => 'globalpreferences',
			'format' => 'json',
		], $params );
		// Necessary despite the API request not actually needing a title.
		$this->apiContext->setTitle( Title::makeTitle( 0, 'GlobalPreferences' ) );
		// Save the initial preferences to the database.
		$storage = new Storage( $this->user->getId() );
		$storage->save( $initialPrefs, array_keys( $initialPrefs ) );
		// Verify that the initial preferences are as expected.
		$this->assertArrayEquals( $initialPrefs, $storage->load( true ) );
		// Make the API request, then check that the preferences have been updated.
		$this->doApiRequestWithToken( $params, null, $this->user );
		$this->assertArrayEquals( $expectedPrefs, $storage->load( true ) );
	}

	public function provideExecute(): Generator {
		yield 'set userjs' => [
			[ 'change' => 'userjs-foo=bar' ],
			[],
			[ 'userjs-foo' => 'bar' ]
		];
		yield 'unset userjs' => [
			[ 'change' => 'userjs-foo' ],
			[ 'userjs-foo' => 'bar' ],
			[]
		];
		yield 'set multiple' => [
			[ 'change' => 'userjs-foo=bar|userjs-baz=qux' ],
			[],
			[ 'userjs-foo' => 'bar', 'userjs-baz' => 'qux' ]
		];
		yield 'set and unset multiple' => [
			[ 'change' => 'userjs-foo=bar|userjs-baz' ],
			[ 'userjs-baz' => 'qux' ],
			[ 'userjs-foo' => 'bar' ]
		];
		yield 'reset all' => [
			[ 'reset' => '1' ],
			[ 'userjs-foo' => 'bar', 'examplepref' => 'bar' ],
			[]
		];
		yield 'reset kind userjs' => [
			[ 'reset' => '1', 'resetkinds' => 'userjs' ],
			[ 'userjs-foo' => 'bar', 'examplepref' => 'baz' ],
			[ 'examplepref' => 'baz' ]
		];
	}

}
