<?php

namespace GlobalPreferences\Test;

use GlobalPreferences\GlobalPreferencesFactory;
use GlobalPreferences\Storage;
use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;
use RequestContext;
use Title;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \GlobalPreferences\GlobalPreferencesFactory
 * @covers \GlobalPreferences\Storage
 * @group GlobalPreferences
 * @group Database
 */
class GlobalPreferencesTest extends MediaWikiTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgCentralIdLookupProvider' => 'local',
		] );
	}

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
		$gpStorage->save( [ 'testpref' => 'test' ], [ 'testpref' ] );
		$this->assertCount( 1, $gpStorage->load() );

		// Save different ones, and it should overwrite.
		$gpStorage->save( [ 'testpref2' => 'test2' ], [ 'testpref', 'testpref2' ] );
		$this->assertCount( 1, $gpStorage->load() );
		$gpStorage->save(
			[ 'testpref2' => 'test2', 'testpref3' => 'test3' ],
			[ 'testpref2', 'testpref3' ]
		);
		$this->assertCount( 2, $gpStorage->load() );

		// Delete all (in two stages).
		$gpStorage->delete( [ 'testpref' ] );
		$this->assertEquals(
			[ 'testpref2' => 'test2', 'testpref3' => 'test3' ],
			$gpStorage->load()
		);
		$gpStorage->delete();
		$this->assertEmpty( $gpStorage->load() );
	}

	public function testUserPreference() {
		$user = $this->getTestUser()->getUser();
		$services = $this->getServiceContainer();
		/** @var GlobalPreferencesFactory $globalPreferences */
		$globalPreferences = $services->getPreferencesFactory();
		$globalPreferences->setAutoGlobals( [] );
		// Set up the context.
		// Once preference definitions don't require the context, this can be removed.
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Test' ) );

		// Confirm the site default.
		$this->assertEquals( 'en', $user->getOption( 'language' ) );
		$this->assertEquals( [], $globalPreferences->getGlobalPreferencesValues( $user ) );

		// Set a local preference.
		$userOptionsManager = $services->getUserOptionsManager();
		$userOptionsManager->setOption( $user, 'language', 'bn' );
		$userOptionsManager->saveOptions( $user );
		$this->assertEquals( 'bn', $user->getOption( 'language' ) );

		// Set it to be global (with a different value).
		$globalPreferences->setGlobalPreferences( $user, [ 'language' => 'de' ], $context );
		$this->assertEquals(
			[ 'language' => 'de' ],
			$globalPreferences->getGlobalPreferencesValues( $user )
		);
		$this->assertEquals( 'de', $user->getOption( 'language' ) );
		$globalPreferences->setGlobalPreferences( $user, [ 'language' => 'ru' ], $context );
		$this->assertEquals( 'ru', $user->getOption( 'language' ) );

		// Then unglobalize it, and it should return to the local value.
		$globalPreferences->setGlobalPreferences( $user, [], $context );
		$this->assertEquals( [], $globalPreferences->getGlobalPreferencesValues( $user ) );
		// @TODO Instance caching on User doesn't clear User::$mOptionOverrides
		// $this->assertEquals( 'bn', $user->getOption( 'language' ) );
	}

	public function testAutoGlobals() {
		$user = $this->getTestUser()->getUser();
		$services = $this->getServiceContainer();
		/** @var GlobalPreferencesFactory $globalPreferences */
		$globalPreferences = $services->getPreferencesFactory();
		$globalPreferences->setAutoGlobals( [] );
		// Set up the context.
		// Once preference definitions don't require the context, this can be removed.
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Test' ) );

		// Set it to be global (with a different value).
		$globalPreferences->setGlobalPreferences( $user, [ 'language' => 'de' ], $context );
		$this->assertEquals(
			[ 'language' => 'de' ],
			$globalPreferences->getGlobalPreferencesValues( $user )
		);
		$this->assertEquals( 'de', $user->getOption( 'language' ) );
		$globalPreferences->setGlobalPreferences( $user, [ 'language' => 'ru' ], $context );
		$this->assertEquals( 'ru', $user->getOption( 'language' ) );

		// Make it autoglobal
		$globalPreferences->setAutoGlobals( [ 'language' ] );
		$userOptionsManager = $services->getUserOptionsManager();
		$userOptionsManager->setOption( $user, 'language', 'sq' );
		$userOptionsManager->saveOptions( $user );
		$this->assertEquals(
			[ 'language' => 'sq' ],
			$globalPreferences->getGlobalPreferencesValues( $user )
		);
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
				'Ignore preferences with disallowed names',
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
