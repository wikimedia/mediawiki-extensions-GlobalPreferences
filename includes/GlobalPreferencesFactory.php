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

namespace GlobalPreferences;

use CentralIdLookup;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Preferences\DefaultPreferencesFactory;
use RequestContext;
use SpecialPage;
use User;

/**
 * Global preferences.
 * @package GlobalPreferences
 */
class GlobalPreferencesFactory extends DefaultPreferencesFactory {

	/** @var User */
	protected $user;

	/**
	 * "bad" preferences that we should remove from
	 * Special:GlobalPrefs
	 * @var array
	 */
	protected $prefsBlacklist = [
		// Stored in user table, doesn't work yet
		'realname',
		// @todo Show CA user id / shared user table id?
		'userid',
		// @todo Show CA global groups instead?
		'usergroups',
		// @todo Should global edit count instead?
		'editcount',
		'registrationdate',
		// Signature could be global, but links in it are too likely to break.
		'nickname',
		'fancysig',
	];

	/**
	 * Preference types that we should not add a checkbox for
	 * @var array
	 */
	protected $typeBlacklist = [
		'info',
		'hidden',
		'api',
	];

	/**
	 * Preference classes that are allowed to be global
	 * @var array
	 */
	protected $classWhitelist = [
		'HTMLSelectOrOtherField',
		'CirrusSearch\HTMLCompletionProfileSettings',
		'NewHTMLCheckField',
		'HTMLFeatureField',
		'HTMLCheckMatrix',
	];

	/**
	 * Set the preferences user.
	 * Note that not many of this class's methods use this, and you have to pass $user again.
	 * @TODO This should really be higher up the class hierarchy.
	 * @param User $user The user.
	 */
	public function setUser( User $user ) {
		$this->user = $user;
	}

	/**
	 * Get all user preferences.
	 * @param User $user The user.
	 * @param IContextSource $context The preferences page.
	 * @return array|null
	 */
	public function getFormDescriptor( User $user, IContextSource $context ) {
		$this->setUser( $user );
		$globalPrefNames = array_keys( $this->getGlobalPreferencesValues() );
		$preferences = parent::getFormDescriptor( $user, $context );
		if ( $this->onGlobalPrefsPage() ) {
			return $this->getPreferencesGlobal( $preferences, $globalPrefNames );
		}
		return $this->getPreferencesLocal( $preferences, $globalPrefNames );
	}

	/**
	 * Add help-text to the local preferences where they're globalized,
	 * and add the link to Special:GlobalPreferences to the personal preferences tab.
	 * @param mixed[][] $preferences The preferences array.
	 * @param string[] $globalPrefNames The names of those preferences that are already global.
	 * @return mixed[][]
	 */
	protected function getPreferencesLocal( $preferences, $globalPrefNames ) {
		foreach ( $preferences as $name => $def ) {
			// If this has been set globally.
			if ( in_array( $name, $globalPrefNames ) ) {
				// Disable this preference.
				$preferences[$name]['disabled'] = true;

				// Append a help message.
				$help = '';
				if ( isset( $preferences[$name]['help-message'] ) ) {
					$help .= wfMessage( $preferences[$name]['help-message'] )->parse() . '<br />';
				} elseif ( isset( $preferences[$name]['help'] ) ) {
					$help .= $preferences[$name]['help'] . '<br />';
				}

				// Create a link to the relevant section of GlobalPreferences.
				$section = substr( $def['section'], 0, strpos( $def['section'], '/' ) );
				$secFragment = 'mw-prefsection-' . $section;

				// Set the new full help text.
				$help .= wfMessage( 'globalprefs-set-globally', [ $secFragment ] )->parse();
				$preferences[$name]['help'] = $help;
				unset( $preferences[$name]['help-message'] );
			}
		}

		// Add a link to GlobalPreferences to the local preferences form.
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$preferences['global-info'] = [
			'type' => 'info',
			'section' => 'personal/info',
			'label-message' => 'globalprefs-info-label',
			'raw' => true,
			'default' => $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'GlobalPreferences' ),
				wfMessage( 'globalprefs-info-link' )->escaped()
			),
		];

		return $preferences;
	}

	/**
	 * Add the '-global' counterparts to all preferences.
	 * @param mixed[][] $preferences The preferences array.
	 * @param string[] $globalPrefNames The names of those preferences that are already global.
	 * @return mixed[][]
	 */
	protected function getPreferencesGlobal( $preferences, $globalPrefNames ) {
		// Add all corresponding new global fields.
		$allPrefs = [];
		foreach ( $preferences as $pref => $def ) {
			// Ignore unwanted preferences.
			if ( !$this->isGlobalizablePreference( $pref, $def ) ) {
				continue;
			}
			// Create the new preference.
			$allPrefs[$pref.'-global'] = [
				'type' => 'toggle',
				// Make the tooltip and the label the same, because the label is normally hidden.
				'tooltip' => 'globalprefs-check-label',
				'label-message' => 'tooltip-globalprefs-check-label',
				'default' => in_array( $pref, $globalPrefNames ),
				'section' => $def['section'],
				'cssclass' => 'mw-globalprefs-global-check mw-globalprefs-checkbox-for-' . $pref,
			];

			$allPrefs[$pref] = $def;
		}
		return $allPrefs;
	}

	/**
	 * Checks whether the given preference is globalizable.
	 *
	 * @param string $name Preference name
	 * @param mixed[] &$info Preference description, by reference to avoid unnecessary cloning
	 * @return bool
	 */
	protected function isGlobalizablePreference( $name, &$info ) {
		// Preferences can opt out of being globalized by setting the 'noglobal' flag.
		$hasOptedOut = ( isset( $info['noglobal'] ) && $info['noglobal'] === true );

		$isAllowedType = isset( $info['type'] )
						 && !in_array( $info['type'], $this->typeBlacklist )
						 && !in_array( $name, $this->prefsBlacklist );

		$isAllowedClass = isset( $info['class'] )
						  && in_array( $info['class'], $this->classWhitelist );

		$endsInGlobal = ( substr( $name, -strlen( '-global' ) ) === '-global' );

		return !$hasOptedOut && !$endsInGlobal && ( $isAllowedType || $isAllowedClass );
	}

	/**
	 * Checks if the user is globalized.
	 * @return bool
	 */
	public function isUserGlobalized() {
		if ( $this->user->isAnon() ) {
			// No prefs for anons, sorry :(
			return false;
		}
		return $this->getUserID() !== 0;
	}

	/**
	 * Gets the user's ID that we're using in the table
	 * Returns 0 if the user is not global
	 * @return int
	 */
	public function getUserID() {
		$lookup = CentralIdLookup::factory();
		return $lookup->centralIdFromLocalUser( $this->user, CentralIdLookup::AUDIENCE_RAW );
	}

	/**
	 * Get the user's global preferences.
	 * @return string[]|bool Array keyed by preference name, or false if not found.
	 */
	public function getGlobalPreferencesValues() {
		$id = $this->getUserID();
		if ( !$id ) {
			return false;
		}
		$storage = new Storage( $id );
		return $storage->load();
	}

	/**
	 * Save the user's global preferences.
	 * @param array $newGlobalPrefs Array keyed by preference name.
	 * @return bool True on success, false if the user isn't global.
	 */
	public function setGlobalPreferences( $newGlobalPrefs ) {
		$id = $this->getUserID();
		if ( !$id ) {
			return false;
		}
		$storage = new Storage( $this->getUserID() );
		$storage->save( $newGlobalPrefs );
		$this->user->clearInstanceCache();
		return true;
	}

	/**
	 * Deletes all of a user's global preferences.
	 * Assumes that the user is globalized.
	 */
	public function resetGlobalUserSettings() {
		$storage = new Storage( $this->getUserID() );
		$storage->delete();
	}

	/**
	 * Convenience function to check if we're on the global prefs page.
	 * @param IContextSource $context The context to use; if not set main request context is used.
	 * @return bool
	 */
	public function onGlobalPrefsPage( $context = null ) {
		$context = $context ?: RequestContext::getMain();
		return $context->getTitle() && $context->getTitle()->isSpecial( 'GlobalPreferences' );
	}

	/**
	 * Convenience function to check if we're on the local
	 * prefs page
	 *
	 * @param IContextSource $context The context to use; if not set main request context is used.
	 * @return bool
	 */
	public function onLocalPrefsPage( $context = null ) {
		$context = $context ?: RequestContext::getMain();
		return $context->getTitle()
		&& $context->getTitle()->isSpecial( 'Preferences' );
	}
}
