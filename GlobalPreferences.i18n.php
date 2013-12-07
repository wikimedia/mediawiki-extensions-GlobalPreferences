<?php

$messages = array();

/** English
 * @author Kunal Mehta
 */
$messages['en'] = array(
	'globalprefs-desc' => 'Allows users to set global preferences',
	'globalprefs-set-globally' => 'This preference has been set globally and must be modified through [[Special:GlobalPreferences|your global preferences]].',
	'globalprefs-check-label' => 'Use this preference on all wikis.',
	'globalprefs-error-header' => 'Error',
	'globalprefs-notglobal' => 'Your account is not a global account and cannot set global preferences.',
	'globalpreferences' => 'Global preferences',
);

/** Message documentation (Message documentation)
 * @author Kunal Mehta
 */
$messages['qqq'] = array(
	'globalprefs-desc' => '{{desc|name=Global Preferences|url=https://www.mediawiki.org/wiki/Extension:GlobalPreferences}}',
	'globalprefs-set-globally' => 'Help message below a disabled preference option instructing the user to change it at their global preferences page.',
	'globalprefs-check-label' => 'Label for a checkbox that enables the user to save that preference globally.',
	'globalprefs-error-header' => 'Page title for error message.

{{Identical|error}}',
	'globalprefs-notglobal' => 'Error message a user sees if they don not have a global account.',
	'globalpreferences' => '{{doc-special|GlobalPreferences}}',
);
