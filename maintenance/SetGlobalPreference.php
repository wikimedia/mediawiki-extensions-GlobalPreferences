<?php

declare( strict_types=1 );

namespace GlobalPreferences\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\Options\UserOptionsManager;

// @codeCoverageIgnoreStart
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname( __DIR__, 3 );
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Set a global preference to a given value for a list of users.
 *
 * @ingroup Maintenance
 */
class SetGlobalPreference extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Set a global preference to a given value for a list of users ' .
			'loaded from a file.'
		);
		$this->addOption(
			'option',
			'The name of the global preference to set',
			true,
			true
		);
		$this->addOption(
			'value',
			'The value to set the preference to',
			true,
			true
		);
		$this->addOption(
			'names-file',
			'Path to a file containing user names, one per line',
			true,
			true
		);
		$this->addOption( 'dry', 'Do not save changes to the database' );
		$this->setBatchSize( 500 );
		$this->requireExtension( 'GlobalPreferences' );
	}

	public function execute(): void {
		$option = $this->getOption( 'option' );
		$value = $this->getOption( 'value' );
		$filePath = $this->getOption( 'names-file' );
		$dryRun = $this->hasOption( 'dry' );

		$services = $this->getServiceContainer();

		$knownOptions = $services->getUserOptionsLookup()->getDefaultOptions();
		if ( !array_key_exists( $option, $knownOptions ) ) {
			$this->fatalError( "'$option' is not a known preference." );
		}

		if ( !is_readable( $filePath ) ) {
			$this->fatalError( "Cannot read file: $filePath" );
		}

		$userOptionsManager = $services->getUserOptionsManager();
		$actorStore = $services->getActorStore();
		$settingWord = $dryRun ? 'Would set' : 'Setting';

		if ( !$dryRun ) {
			$this->beginTransactionRound( __METHOD__ );
		}

		$file = fopen( $filePath, 'r' );
		$processedCount = 0;
		try {
			while ( true ) {
				$line = fgets( $file );
				if ( $line === false ) {
					break;
				}
				$userName = trim( $line );
				if ( $userName === '' ) {
					continue;
				}

				$userIdentity = $actorStore->getUserIdentityByName( $userName );
				if ( !$userIdentity ) {
					$this->output( "Skipping $userName: no such account\n" );
					continue;
				}

				$this->output( "$settingWord $option for $userName to '$value'\n" );
				$processedCount++;
				if ( !$dryRun ) {
					$userOptionsManager->setOption(
						$userIdentity,
						$option,
						$value,
						UserOptionsManager::GLOBAL_CREATE
					);
					$userOptionsManager->saveOptions( $userIdentity );
					if ( $processedCount % $this->getBatchSize() === 0 ) {
						$this->commitTransactionRound( __METHOD__ );
						$this->beginTransactionRound( __METHOD__ );
					}
				}
			}
		} finally {
			fclose( $file );
			if ( !$dryRun ) {
				$this->commitTransactionRound( __METHOD__ );
			}
		}

		if ( $processedCount === 0 ) {
			$this->output( "No users processed.\n" );
			return;
		}

		if ( $dryRun ) {
			$this->output( "Would update preferences for $processedCount users.\n" );
		} else {
			$this->output( "Updated preferences for $processedCount users.\n" );
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = SetGlobalPreference::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
