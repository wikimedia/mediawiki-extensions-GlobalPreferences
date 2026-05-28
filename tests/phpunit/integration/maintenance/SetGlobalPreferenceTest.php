<?php

declare( strict_types=1 );

namespace GlobalPreferences\Tests\Integration\Maintenance;

use GlobalPreferences\Maintenance\SetGlobalPreference;
use GlobalPreferences\Storage;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\User\CentralId\CentralIdLookup;

/**
 * @covers \GlobalPreferences\Maintenance\SetGlobalPreference
 * @group Database
 * @group GlobalPreferences
 */
class SetGlobalPreferenceTest extends MaintenanceBaseTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			MainConfigNames::CentralIdLookupProvider => 'local',
			'GlobalPreferencesDB' => false,
		] );

		$this->setMainCache( CACHE_NONE );
	}

	protected function getMaintenanceClass(): string {
		return SetGlobalPreference::class;
	}

	public function testSetsPreferenceForUsersInFile(): void {
		$user1 = $this->getMutableTestUser()->getUser();
		$user2 = $this->getMutableTestUser()->getUser();

		$tempFile = $this->getNewTempFile();
		file_put_contents( $tempFile, $user1->getName() . "\n" . $user2->getName() . "\n" );

		$this->maintenance->setOption( 'option', 'skin' );
		$this->maintenance->setOption( 'value', 'vector' );
		$this->maintenance->setOption( 'names-file', $tempFile );
		$this->expectOutputRegex( '/Updated preferences for 2 users\./' );
		$this->maintenance->execute();

		$centralIdLookup = $this->getServiceContainer()->getCentralIdLookup();
		$storage1 = new Storage(
			$centralIdLookup->centralIdFromLocalUser( $user1, CentralIdLookup::AUDIENCE_RAW )
		);
		$this->assertSame( 'vector', $storage1->loadFromDB()['skin'] ?? null );

		$storage2 = new Storage(
			$centralIdLookup->centralIdFromLocalUser( $user2, CentralIdLookup::AUDIENCE_RAW )
		);
		$this->assertSame( 'vector', $storage2->loadFromDB()['skin'] ?? null );
	}

	public function testSkipsUserWithNoCentralAccount(): void {
		$tempFile = $this->getNewTempFile();
		file_put_contents( $tempFile, "NonExistentUser12345\n" );

		$this->maintenance->setOption( 'option', 'skin' );
		$this->maintenance->setOption( 'value', 'vector' );
		$this->maintenance->setOption( 'names-file', $tempFile );
		$this->expectOutputRegex( '/Skipping NonExistentUser12345: no such account/' );
		$this->maintenance->execute();
	}

	public function testDryRunDoesNotWriteToDatabase(): void {
		$user = $this->getMutableTestUser()->getUser();

		$tempFile = $this->getNewTempFile();
		file_put_contents( $tempFile, $user->getName() . "\n" );

		$this->maintenance->setOption( 'option', 'skin' );
		$this->maintenance->setOption( 'value', 'vector' );
		$this->maintenance->setOption( 'names-file', $tempFile );
		$this->maintenance->setOption( 'dry', true );
		$this->expectOutputRegex( '/Would set skin for .+ to \'vector\'/' );
		$this->expectOutputRegex( '/Would update preferences for 1 users\./' );
		$this->maintenance->execute();

		$centralIdLookup = $this->getServiceContainer()->getCentralIdLookup();
		$storage = new Storage(
			$centralIdLookup->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW )
		);
		$this->assertArrayNotHasKey( 'skin', $storage->loadFromDB() );
	}

	public function testFatalErrorForUnknownOption(): void {
		$tempFile = $this->getNewTempFile();
		file_put_contents( $tempFile, "SomeUser\n" );

		$this->maintenance->setOption( 'option', 'this-option-does-not-exist' );
		$this->maintenance->setOption( 'value', '1' );
		$this->maintenance->setOption( 'names-file', $tempFile );

		$this->expectCallToFatalError();
		$this->expectOutputRegex( '/not a known preference/' );
		$this->maintenance->execute();
	}

	public function testFatalErrorWhenFileCannotBeRead(): void {
		$this->maintenance->setOption( 'option', 'skin' );
		$this->maintenance->setOption( 'value', 'vector' );
		$this->maintenance->setOption( 'names-file', '/nonexistent/path/to/file.txt' );

		$this->expectCallToFatalError();
		$this->expectOutputRegex( '/Cannot read file:/' );
		$this->maintenance->execute();
	}

	public function testEmptyFileOutput(): void {
		$tempFile = $this->getNewTempFile();
		file_put_contents( $tempFile, "\n\n   \n" );

		$this->maintenance->setOption( 'option', 'skin' );
		$this->maintenance->setOption( 'value', 'vector' );
		$this->maintenance->setOption( 'names-file', $tempFile );
		$this->expectOutputRegex( '/No users processed\./' );
		$this->maintenance->execute();
	}

	public function testIgnoresBlankLinesAndWhitespace(): void {
		$user = $this->getMutableTestUser()->getUser();

		$tempFile = $this->getNewTempFile();
		file_put_contents( $tempFile, "\n  \n" . $user->getName() . "\n\n" );

		$this->maintenance->setOption( 'option', 'skin' );
		$this->maintenance->setOption( 'value', 'monobook' );
		$this->maintenance->setOption( 'names-file', $tempFile );
		$this->maintenance->execute();

		$centralIdLookup = $this->getServiceContainer()->getCentralIdLookup();
		$storage = new Storage(
			$centralIdLookup->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW )
		);
		$this->assertSame( 'monobook', $storage->loadFromDB()['skin'] ?? null );
	}

	/** @dataProvider provideBatchesProperly */
	public function testBatchesProperly( int $batchSize, int $numUsers ): void {
		$users = [];
		$userNames = [];
		for ( $i = 0; $i < $numUsers; $i++ ) {
			$user = $this->getMutableTestUser()->getUser();
			$users[] = $user;
			$userNames[] = $user->getName();
		}

		$tempFile = $this->getNewTempFile();
		file_put_contents( $tempFile, implode( "\n", $userNames ) );

		// "Special" options such as --batch-size take no effect when set with setOption
		$this->maintenance->loadWithArgv( [
			'--batch-size', $batchSize,
			'--option', 'skin',
			'--value', 'vector',
			'--names-file', $tempFile,
		] );
		$this->expectOutputRegex( "/Updated preferences for $numUsers users\./" );
		$this->maintenance->execute();

		$centralIdLookup = $this->getServiceContainer()->getCentralIdLookup();
		foreach ( $users as $user ) {
			$storage = new Storage(
				$centralIdLookup->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW )
			);
			$this->assertSame( 'vector', $storage->loadFromDB()['skin'] ?? null );
		}
	}

	public static function provideBatchesProperly(): iterable {
		yield 'Number of users is not a multiple of batch size' => [
			'batchSize' => 2,
			'numUsers' => 5,
		];
		yield 'Number of users is a multiple of batch size' => [
			'batchSize' => 2,
			'numUsers' => 4,
		];
	}
}
