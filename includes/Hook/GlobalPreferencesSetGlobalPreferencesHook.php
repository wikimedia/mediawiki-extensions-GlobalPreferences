<?php

namespace GlobalPreferences\Hook;

use MediaWiki\User\UserIdentity;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "GlobalPreferencesSetGlobalPreferences" to register handlers implementing
 * this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface GlobalPreferencesSetGlobalPreferencesHook {
	/**
	 * This hook is called just after saving global preferences.
	 *
	 * Hook handlers can inspect the old preferences and the new preferences, but not
	 * alter them.
	 *
	 * @since 1.44
	 *
	 * @param UserIdentity $user The user for which the global preferences have just been saved
	 * @param array $oldPreferences The user's old preferences that were replaced
	 * @param array $newPreferences The user's new preferences as an associative array
	 * @return void This hook must not abort, it must return no value
	 * @codeCoverageIgnore Cannot be annotated as covered.
	 */
	public function onGlobalPreferencesSetGlobalPreferences(
		UserIdentity $user,
		array $oldPreferences,
		array $newPreferences
	): void;
}
