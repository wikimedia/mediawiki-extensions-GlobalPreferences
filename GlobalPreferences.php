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

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'GlobalPreferences' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['GlobalPreferences'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['GlobalPreferencesAlias'] = __DIR__ . '/GlobalPreferences.alias.php';
	wfWarn(
		'Deprecated PHP entry point used for GlobalPreferences extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return true;
} else {
	die( 'This version of the GlobalPreferences extension requires MediaWiki 1.25+' );
}