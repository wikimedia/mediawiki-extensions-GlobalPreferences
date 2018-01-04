<?php

namespace GlobalPreferences\Test;

use GlobalPreferences\GlobalPreferencesFactory;
use GlobalPreferences\Storage;
use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group GlobalPreferences
 */
class GlobalPreferencesTest extends MediaWikiTestCase {

	public function testService() {
		$factory = MediaWikiServices::getInstance()->getPreferencesFactory();
		$this->assertInstanceOf( GlobalPreferencesFactory::class, $factory );
	}

	public function testStorage() {
		$user = $this->getTestUser()->getUser();
		$gpStorage = new Storage( $user->getId() );

		// No prefs to start with.
		$this->assertEmpty( $gpStorage->load() );

		// Save one, and retrieve it.
		$gpStorage->save( [ 'testpref' => 'test' ] );
		$this->assertCount( 1, $gpStorage->load() );

		// Save different ones, and it should overwrite.
		$gpStorage->save( [ 'testpref2' => 'test2' ] );
		$this->assertCount( 1, $gpStorage->load() );
		$gpStorage->save( [ 'testpref2' => 'test2', 'testpref3' => 'test3' ] );
		$this->assertCount( 2, $gpStorage->load() );

		// Delete all
		$gpStorage->delete();
		$this->assertEmpty( $gpStorage->load() );
	}

	public function testUserPreference() {
		$user = $this->getTestUser()->getUser();
		/** @var GlobalPreferencesFactory $globalPreferences */
		$globalPreferences = MediaWikiServices::getInstance()->getPreferencesFactory();
		$globalPreferences->setUser( $user );

		// Confirm the site default.
		$this->assertEquals( 'en', $user->getOption( 'language' ) );

		// Set a local preference.
		$user->setOption( 'language', 'bn' );
		$user->saveSettings();
		$this->assertEquals( 'bn', $user->getOption( 'language' ) );

		// Set it to be global (with a different value).
		$globalPreferences->setGlobalPreferences( [ 'language' => 'de' ] );
		$this->assertEquals( [ 'language' => 'de' ], $globalPreferences->getGlobalPreferencesValues() );
		$this->assertEquals( 'de', $user->getOption( 'language' ) );
		$globalPreferences->setGlobalPreferences( [ 'language' => 'ru' ] );
		$this->assertEquals( 'ru', $user->getOption( 'language' ) );

		// Then unglobalize it, and it should return to the local value.
		$globalPreferences->setGlobalPreferences( [] );
		$this->assertEquals( [], $globalPreferences->getGlobalPreferencesValues() );
		// @TODO Instance caching on User doesn't clear User::$mOptionOverrides
		// $this->assertEquals( 'bn', $user->getOption( 'language' ) );
	}

	/**
	 * @dataProvider provideIsGlobalizablePreference
	 *
	 * @param string $message
	 * @param bool $expected
	 * @param string $name
	 * @param array $info
	 */
	public function testIsGlobalizablePreference( $message, $expected, $name, array $info ) {
		/** @var GlobalPreferencesFactory $globalPreferences */
		$globalPreferences = TestingAccessWrapper::newFromObject(
			MediaWikiServices::getInstance()->getPreferencesFactory()
		);

		// Not calling directly because TestingAccessWrapper strips reference otherwise
		$result = call_user_func_array(
			[ $globalPreferences, 'isGlobalizablePreference' ],
			[ $name, &$info ]
		);
		$this->assertEquals( $expected, $result, $message );
	}

	public function provideIsGlobalizablePreference() {
		return [
			// message, expected, name, info
			[
				'Globalize simple text preferences',
				true,
				'foo',
				[
					'type' => 'text',
				],
			],
			[
				'Globalize select controls',
				true,
				'language',
				[
					'type' => 'select',
					'options' => [ 'foo' => 'bar', 'baz' => 'quux' ],
				],
			],
			[
				'Globalize preferences with known class',
				true,
				'foo',
				[
					'class' => 'HTMLCheckMatrix',
				],
			],
			[
				'Ignore info "preferences"',
				false,
				'username',
				[
					"label-message" => [
						"username",
						"Foo"
					],
					"default" => "Foo",
					"section" => "personal/info",
				],
			],
			[
				'Ignore preferences explicitly marked as non-global',
				false,
				'foo',
				[
					'type' => 'text',
					'noglobal' => true,
				],
			],
			[
				'Ignore checkboxes added by this very extension',
				false,
				'foo-global',
				[
					'type' => 'text',
				],
			],
			[
				'Ignore disabled preferences',
				false,
				'foo',
				[
					'type' => 'text',
					'disabled' => true,
				],
			],
			[
				'Globalize preferences with disabled=false',
				true,
				'foo',
				[
					'type' => 'text',
					'disabled' => false,
				],
			],
			[
				'Ignore preferences with blacklisted names',
				false,
				'realname',
				[
					'type' => 'text',
				],
			],
			[
				'Ignore preferences with unknown class',
				false,
				'foo',
				[
					'class' => 'SomethingNew',
				],
			],
		];
	}
}
