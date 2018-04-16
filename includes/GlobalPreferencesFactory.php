<?php
/**
 * Implements global preferences for MediaWiki
 *
 * @author Kunal Mehta <legoktm@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @file
 * @ingroup Extensions
 *
 * Partially based off of work by Werdna
 * https://www.mediawiki.org/wiki/Special:Code/MediaWiki/49790
 */

namespace GlobalPreferences;

use CentralIdLookup;
use IContextSource;
use MapCacheLRU;
use MediaWiki\MediaWikiServices;
use MediaWiki\Preferences\DefaultPreferencesFactory;
use RequestContext;
use SpecialPage;
use User;
use WebRequest;

/**
 * Global preferences.
 * @package GlobalPreferences
 */
class GlobalPreferencesFactory extends DefaultPreferencesFactory {

	/**
	 * The suffix appended to preference names
	 * for the associated preference that tracks whether they have a local override.
	 */
	const LOCAL_EXCEPTION_SUFFIX = '-local-exception';

	/**
	 * The suffix appended to preference names for their global counterparts.
	 */
	const GLOBAL_EXCEPTION_SUFFIX = '-global';

	/** @var User */
	protected $user;

	/** @var MapCacheLRU Runtime cache of users' central IDs. */
	protected static $centralIds;

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
		return $this->getPreferencesLocal( $preferences, $globalPrefNames, $context->getRequest() );
	}

	/**
	 * Add help-text to the local preferences where they're globalized,
	 * and add the link to Special:GlobalPreferences to the personal preferences tab.
	 * @param mixed[][] $preferences The preferences array.
	 * @param string[] $globalPrefNames The names of those preferences that are already global.
	 * @param WebRequest $request The current request, to check for local exceptions being set.
	 * @return mixed[][]
	 */
	protected function getPreferencesLocal( $preferences, $globalPrefNames, WebRequest $request ) {
		$this->logger->debug( "Creating local preferences array for '{$this->user->getName()}'" );
		$modifiedPrefs = [];
		foreach ( $preferences as $name => $def ) {
			$modifiedPrefs[$name] = $def;

			// If this has been set globally.
			if ( in_array( $name, $globalPrefNames ) ) {
				// Disable this local preference unless it either
				// A) already has a local exception, or
				// B) a local exception is being enabled in the current request.
				// This is because HTMLForm changes submitted values to their defaults
				// after preferences have been defined here, if a field is disabled.
				$localExName = $name . static::LOCAL_EXCEPTION_SUFFIX;
				$localExValueUser = $this->user->getOption( $localExName );
				$localExValueRequest = $request->getVal( 'wp' . $localExName );
				$modifiedPrefs[$name]['disabled'] = is_null( $localExValueUser )
					&& is_null( $localExValueRequest );

				// Add a new local exception preference after this one.
				$cssClasses = [
					'mw-globalprefs-local-exception',
					'mw-globalprefs-local-exception-for-' . $name,
				];
				$secFragment = static::getSectionFragmentId( $def['section'] );
				$labelMsg = wfMessage( 'globalprefs-set-local-exception', [ $secFragment ] );
				$modifiedPrefs[ $localExName ] = [
					'type' => 'toggle',
					'label-raw' => $labelMsg->parse(),
					'default' => $localExValueUser,
					'section' => $def['section'],
					'cssclass' => implode( ' ', $cssClasses ),
				];
			}
		}
		$preferences = $modifiedPrefs;

		// Add a link to GlobalPreferences to the local preferences form.
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$preferences['global-info'] = [
			'type' => 'info',
			'section' => 'personal/info',
			'label-message' => 'globalprefs-info-label',
			'raw' => true,
			'default' => $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'GlobalPreferences' ),
				wfMessage( 'globalprefs-info-link' )->text()
			),
			'help-message' => 'globalprefs-info-help',
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
			$isGlobal = in_array( $pref, $globalPrefNames );
			$allPrefs[$pref . static::GLOBAL_EXCEPTION_SUFFIX] = [
				'type' => 'toggle',
				// Make the tooltip and the label the same, because the label is normally hidden.
				'tooltip' => 'globalprefs-check-label',
				'label-message' => 'tooltip-globalprefs-check-label',
				'default' => $isGlobal,
				'section' => $def['section'],
				'cssclass' => 'mw-globalprefs-global-check mw-globalprefs-checkbox-for-' . $pref,
			];
			// If this has a local exception, append a help message to say so.
			if ( $isGlobal
				&& $this->user->getOption( $pref . static::LOCAL_EXCEPTION_SUFFIX )
			) {
				$help = '';
				if ( isset( $def['help-message'] ) ) {
					$help .= wfMessage( $def['help-message'] )->parse() . '<br />';
				} elseif ( isset( $def['help'] ) ) {
					$help .= $def['help'] . '<br />';
				}
				// Create a link to the relevant section of GlobalPreferences.
				$secFragment = static::getSectionFragmentId( $def['section'] );
				// Merge the help texts.
				$helpMsg = wfMessage( 'globalprefs-has-local-exception', [ $secFragment ] );
				unset( $def['help-message'] );
				$def['help'] = $help . $helpMsg->parse();
			}

			$allPrefs[$pref] = $def;
		}
		return $allPrefs;
	}

	/**
	 * Get the HTML fragment identifier for a given preferences section. This is the leading part
	 * of the provided section name, up to a slash (if there is one).
	 * @param string $section A section name, as used in a preference definition.
	 * @return string
	 */
	public static function getSectionFragmentId( $section ) {
		$sectionId = preg_replace( '#/.*$#', '', $section );
		return 'mw-prefsection-' . $sectionId;
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
		if ( isset( $info['noglobal'] ) && $info['noglobal'] === true ) {
			return false;
		}

		// Ignore "is global" checkboxes
		if ( static::isGlobalPrefName( $name ) ) {
			return false;
		}

		// If a setting can't be changed, don't bother globalizing it
		if ( isset( $info['disabled'] ) && $info['disabled'] ) {
			return false;
		}

		$isAllowedType = isset( $info['type'] )
						 && !in_array( $info['type'], $this->typeBlacklist )
						 && !in_array( $name, $this->prefsBlacklist );

		$isAllowedClass = isset( $info['class'] )
						  && in_array( $info['class'], $this->classWhitelist );

		return $isAllowedType || $isAllowedClass;
	}

	/**
	 * A convenience function to check if a preference name is for a global one.
	 * @param string $name The name to check.
	 * @return bool
	 */
	public static function isGlobalPrefName( $name ) {
		return static::strEndsWith( $name, static::GLOBAL_EXCEPTION_SUFFIX );
	}

	/**
	 * A convenience function to check if a preference name is for a local-exception preference.
	 * @param string $name The name to check.
	 * @return bool
	 */
	public static function isLocalPrefName( $name ) {
		return static::strEndsWith( $name, static::LOCAL_EXCEPTION_SUFFIX );
	}

	/**
	 * A convenience function to check a string to see if it ends in a given suffix.
	 * @todo This could probably exist somewhere like StringUtils.
	 * @param string $name The name to check.
	 * @param string $suffix The suffix to check for.
	 * @return bool
	 */
	protected static function strEndsWith( $name, $suffix ) {
		$nameSuffix = substr( $name, -strlen( $suffix ) );
		return ( $nameSuffix === $suffix );
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
		$id = $this->user->getId();
		if ( !static::$centralIds instanceof MapCacheLRU ) {
			// Max of 20 is arbitrary and matches what CentralAuth uses.
			static::$centralIds = new MapCacheLRU( 20 );
		}
		if ( static::$centralIds->has( $id ) ) {
			return static::$centralIds->get( $id );
		}
		$lookup = CentralIdLookup::factory();
		$gid = $lookup->centralIdFromLocalUser( $this->user, CentralIdLookup::AUDIENCE_RAW );
		static::$centralIds->set( $id, $gid );
		return $gid;
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
	 * @param IContextSource $context The request context.
	 * @return bool True on success, false if the user isn't global.
	 */
	public function setGlobalPreferences( $newGlobalPrefs, IContextSource $context ) {
		$id = $this->getUserID();
		if ( !$id ) {
			return false;
		}

		// Use a new instance of the current user to fetch the form descriptor because that way
		// we're working with the previous user options and not those that are currently in the
		// process of being saved (we only want the option names here, so don't care what the
		// values are).
		$actualUser = $this->user;
		$this->user = User::newFromId( $this->user->getId() );

		// Save the global options.
		$storage = new Storage( $this->getUserID() );
		$knownPrefs = array_keys( $this->getFormDescriptor( $this->user, $context ) );
		$storage->save( $newGlobalPrefs, $knownPrefs );

		// Return to the actual user object.
		$this->user = $actualUser;
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
