<?php

namespace GlobalPreferences\HookHandler;

use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;

class LoadExtensionSchemaUpdatesHookHandler implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 * @param DatabaseUpdater $updater The database updater.
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$dBname = $config->get( 'DBname' );
		$sharedDB = $config->get( 'SharedDB' );

		// During install, extension registry config is not loaded - T198330
		$globalPreferencesDB = $config->has( 'GlobalPreferencesDB' )
			? $config->get( 'GlobalPreferencesDB' )
			: null;

		// Only add the global_preferences table to the $wgGlobalPreferencesDB or the $wgSharedDB,
		// unless neither of them is set. See also \GlobalPreferences\Storage::getDatabase().
		if ( ( $globalPreferencesDB === null && $sharedDB === null )
			|| $dBname === $globalPreferencesDB
			|| ( $globalPreferencesDB === null && $dBname === $sharedDB )
		) {
			$type = $updater->getDB()->getType();
			$sqlPath = dirname( __DIR__, 2 ) . '/sql';

			$updater->addExtensionTable( 'global_preferences', "$sqlPath/$type/tables-generated.sql" );

			if ( $type === 'mysql' || $type === 'sqlite' ) {
				$updater->dropExtensionIndex( 'global_preferences',
					'global_preferences_user_property',
					"$sqlPath/patch_primary_index.sql"
				);
				$updater->modifyExtensionField( 'global_preferences',
					'gp_user',
					"$sqlPath/$type/patch-gp_user-unsigned.sql"
				);
			}
		}
	}
}
