<?php

namespace GlobalPreferences\Services;

use GlobalPreferences\Hook\GlobalPreferencesSetGlobalPreferencesHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\User\UserIdentity;

class GlobalPreferencesHookRunner implements
	GlobalPreferencesSetGlobalPreferencesHook
{

	public function __construct( private readonly HookContainer $container ) {
	}

	/** @inheritDoc */
	public function onGlobalPreferencesSetGlobalPreferences(
		UserIdentity $user,
		array $oldPreferences,
		array $newPreferences
	): void {
		$this->container->run(
			'GlobalPreferencesSetGlobalPreferences',
			[ $user, $oldPreferences, $newPreferences ],
			[ 'abortable' => false ]
		);
	}
}
