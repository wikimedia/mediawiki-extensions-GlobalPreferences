<?php

/**
 * Implements global preferences for MediaWiki
 *
 * @author Kunal Mehta <legoktm@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @file
 * @ingroup Extensions
 *
 * Partially based off of work by Werdna
 * https://www.mediawiki.org/wiki/Special:Code/MediaWiki/49790
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die;
}

/**
 * Database to store preferences in
 * if null, uses $wgDBname
 */
$wgGlobalPreferencesDB = null;

$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'GlobalPreferences',
	'author' => 'Kunal Mehta',
	'url' => 'https://www.mediawiki.org/wiki/Extension:GlobalPreferences',
	'descriptionmsg' => 'globalprefs-desc',
	'version' => '0.1.0',
);

$wgSpecialPages['GlobalPreferences'] = 'SpecialGlobalPreferences';
$wgMessagesDirs['GlobalPreferences'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['GlobalPreferences'] = __DIR__ . "/GlobalPreferences.i18n.php";
$wgAutoloadClasses['GlobalPreferences'] = __DIR__ . "/GlobalPreferences.body.php";
$wgAutoloadClasses['GlobalPreferencesHooks'] = __DIR__ . "/GlobalPreferences.hooks.php";
$wgAutoloadClasses['SpecialGlobalPreferences'] = __DIR__ . "/SpecialGlobalPreferences.php";

$wgHooks['UserLoadOptions'][] = 'GlobalPreferencesHooks::onUserLoadOptions';
$wgHooks['UserSaveOptions'][] = 'GlobalPreferencesHooks::onUserSaveOptions';
$wgHooks['PreferencesFormPreSave'][] = 'GlobalPreferencesHooks::onPreferencesFormPreSave';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'GlobalPreferencesHooks::onLoadExtensionSchemaUpdates';
$wgExtensionFunctions[] = function() {
	global $wgHooks;
	// Register this as late as possible!
	$wgHooks['GetPreferences'][] = 'GlobalPreferencesHooks::onGetPreferences';
};
