<?php

namespace GlobalPreferences\Services;

use GlobalPreferences\Hook\GlobalPreferencesSetGlobalPreferencesHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\User\UserIdentity;

class GlobalPreferencesHookRunner implements
	GlobalPreferencesSetGlobalPreferencesHook
{

	private HookContainer $container;

	public function __construct( HookContainer $container ) {
		$this->container = $container;
	}

	/** @inheritDoc */
	public function onGlobalPreferencesSetGlobalPreferences(
		UserIdentity $user,
		array $oldPreferences,
		array $newPreferences
	): void {
		$this->container->run(
			'GlobalPreferencesSetGlobalPreferences',
			[ $user, $oldPreferences, $newPreferences ]
		);
	}
}
