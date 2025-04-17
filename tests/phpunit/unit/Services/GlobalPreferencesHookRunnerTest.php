<?php

namespace MediaWiki\GlobalPreferences\Tests\Services\Unit;

use GlobalPreferences\Services\GlobalPreferencesHookRunner;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \GlobalPreferences\Services\GlobalPreferencesHookRunner
 */
class GlobalPreferencesHookRunnerTest extends HookRunnerTestBase {

	public static function provideHookRunners() {
		yield GlobalPreferencesHookRunner::class => [ GlobalPreferencesHookRunner::class ];
	}

}
