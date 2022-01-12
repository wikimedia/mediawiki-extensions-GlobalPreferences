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
use Exception;
use IContextSource;
use LogicException;
use MapCacheLRU;
use MediaWiki\MediaWikiServices;
use MediaWiki\Preferences\DefaultPreferencesFactory;
use MediaWiki\User\UserIdentity;
use OOUI\ButtonWidget;
use RequestContext;
use SpecialPage;
use Status;
use User;

/**
 * Global preferences.
 * @package GlobalPreferences
 */
class GlobalPreferencesFactory extends DefaultPreferencesFactory {

	/**
	 * The suffix appended to preference names
	 * for the associated preference that tracks whether they have a local override.
	 */
	public const LOCAL_EXCEPTION_SUFFIX = '-local-exception';

	/**
	 * The suffix appended to preference names for their global counterparts.
	 */
	public const GLOBAL_EXCEPTION_SUFFIX = '-global';

	/** @var MapCacheLRU Runtime cache of users' central IDs. */
	protected $centralIds;

	/**
	 * @var string[] Names of autoglobal options
	 */
	protected $autoGlobals = [];

	/**
	 * "bad" preferences that we should remove from
	 * Special:GlobalPrefs
	 * @var array
	 */
	protected $disallowedPreferences = [
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
	protected $typesPrevented = [
		'info',
		'hidden',
		'api',
	];

	/**
	 * Preference classes that are allowed to be global
	 * @var array
	 */
	protected $allowedClasses = [
		\HTMLSelectOrOtherField::class,
		\MediaWiki\Extension\BetaFeatures\NewHTMLCheckField::class,
		\MediaWiki\Extension\BetaFeatures\HTMLFeatureField::class,
		\HTMLCheckMatrix::class,
		\Vector\HTMLForm\Fields\HTMLLegacySkinVersionField::class,
	];

	/**
	 * Sets the list of options for which setting the local value should transparently update
	 * the global value.
	 *
	 * @param string[] $list
	 */
	public function setAutoGlobals( array $list ) {
		$this->autoGlobals = $list;
	}

	/**
	 * Get all user preferences.
	 * @param User $user
	 * @param IContextSource $context The current request context
	 * @return array|null
	 */
	public function getFormDescriptor( User $user, IContextSource $context ) {
		$globalPrefs = $this->getGlobalPreferencesValues( $user, Storage::SKIP_CACHE );
		// The above function can return false
		$globalPrefNames = $globalPrefs ? array_keys( $globalPrefs ) : [];
		$preferences = parent::getFormDescriptor( $user, $context );
		if ( $this->onGlobalPrefsPage( $context ) ) {
			if ( $globalPrefs === false ) {
				throw new Exception(
					"Attempted to load global preferences page for {$user->getName()} whose "
					. 'preference values failed to load'
				);
			}
			return $this->getPreferencesGlobal( $user, $preferences, $globalPrefs, $context );
		}
		return $this->getPreferencesLocal( $user, $preferences, $globalPrefNames, $context );
	}

	/**
	 * Lazy-init getter for central ID instance cache
	 * @return MapCacheLRU
	 */
	protected function getCache() {
		if ( !$this->centralIds ) {
			// Max of 20 is arbitrary and matches what CentralAuth uses.
			$this->centralIds = new MapCacheLRU( 20 );
		}
		return $this->centralIds;
	}

	/**
	 * Add help-text to the local preferences where they're globalized,
	 * and add the link to Special:GlobalPreferences to the personal preferences tab.
	 * @param User $user
	 * @param mixed[][] $preferences The preferences array.
	 * @param string[] $globalPrefNames The names of those preferences that are already global.
	 * @param IContextSource $context The current request context
	 * @return mixed[][]
	 */
	protected function getPreferencesLocal(
		User $user,
		array $preferences,
		array $globalPrefNames,
		IContextSource $context
	) {
		$this->logger->debug( "Creating local preferences array for '{$user->getName()}'" );
		$modifiedPrefs = [];
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		foreach ( $preferences as $name => $def ) {
			$modifiedPrefs[$name] = $def;
			if ( !isset( $def['section'] ) ) {
				// Preference has no control in the UI
				continue;
			}
			// If this has been set globally.
			if ( in_array( $name, $globalPrefNames ) ) {
				// Disable this local preference unless it either
				// A) already has a local exception, or
				// B) a local exception is being enabled in the current request.
				// This is because HTMLForm changes submitted values to their defaults
				// after preferences have been defined here, if a field is disabled.
				$localExName = $name . static::LOCAL_EXCEPTION_SUFFIX;
				$localExValueUser = $userOptionsLookup->getBoolOption( $user, $localExName );
				$localExValueRequest = $context->getRequest()->getVal( 'wp' . $localExName );
				$modifiedPrefs[$name]['disabled'] = !$localExValueUser && $localExValueRequest === null;

				// Add a new local exception preference after this one.
				$cssClasses = [
					'mw-globalprefs-local-exception',
					'mw-globalprefs-local-exception-for-' . $name,
				];
				$section = $def['section'];
				$secFragment = static::getSectionFragmentId( $section );
				$labelMsg = $context->msg( 'globalprefs-set-local-exception', [ $secFragment ] );
				$modifiedPrefs[$localExName] = [
					'type' => 'toggle',
					'label-raw' => $labelMsg->parse(),
					'default' => $localExValueUser,
					'cssclass' => implode( ' ', $cssClasses ),
				];
				if ( $section !== '' ) {
					$modifiedPrefs[$localExName]['section'] = $section;
				}
			}
		}
		$preferences = $modifiedPrefs;

		// Add a link to GlobalPreferences to the local preferences form.
		$linkObject = new ButtonWidget( [
			'href' => SpecialPage::getTitleFor( 'GlobalPreferences' )->getLinkURL(),
			'label' => $context->msg( 'globalprefs-info-link' )->text(),
		] );
		$link = $linkObject->toString();

		$preferences['global-info'] = [
			'type' => 'info',
			'section' => 'personal/info',
			'label-message' => 'globalprefs-info-label',
			'raw' => true,
			'default' => $link,
			'help-message' => 'globalprefs-info-help',
		];

		return $preferences;
	}

	/**
	 * Add the '-global' counterparts to all preferences, and override the local exception.
	 * @param User $user
	 * @param mixed[][] $preferences The preferences array.
	 * @param mixed[] $globalPrefs The array of global preferences.
	 * @param IContextSource $context The current request context
	 * @return mixed[][]
	 */
	protected function getPreferencesGlobal(
		User $user,
		array $preferences,
		array $globalPrefs,
		IContextSource $context
	) {
		// Add all corresponding new global fields.
		$allPrefs = [];
		foreach ( $preferences as $pref => $def ) {
			// Ignore unwanted preferences.
			if ( !$this->isGlobalizablePreference( $pref, $def ) ) {
				continue;
			}
			// Create the new preference.
			$isGlobal = isset( $globalPrefs[$pref] );
			$allPrefs[$pref . static::GLOBAL_EXCEPTION_SUFFIX] = [
				'type' => 'toggle',
				// Make the tooltip and the label the same, because the label is normally hidden.
				'tooltip' => 'globalprefs-check-label',
				'label-message' => 'tooltip-globalprefs-check-label',
				'default' => $isGlobal,
				'section' => $def['section'],
				'cssclass' => 'mw-globalprefs-global-check mw-globalprefs-checkbox-for-' . $pref,
				'hide-if' => $def['hide-if'] ?? false,
			];
			// If this has a local exception, override it and append a help message to say so.
			$hasLocalException = MediaWikiServices::getInstance()
				->getUserOptionsLookup()
				->getBoolOption( $user, $pref . static::LOCAL_EXCEPTION_SUFFIX );
			if ( $isGlobal && $hasLocalException ) {
				$def['default'] = $this->getOptionFromUser( $pref, $def, $globalPrefs );
				$help = '';
				if ( isset( $def['help-message'] ) ) {
					$help .= $context->msg( $def['help-message'] )->parse() . '<br />';
				} elseif ( isset( $def['help'] ) ) {
					$help .= $def['help'] . '<br />';
				}
				// Create a link to the relevant section of GlobalPreferences.
				$secFragment = static::getSectionFragmentId( $def['section'] );
				// Merge the help texts.
				$helpMsg = $context->msg( 'globalprefs-has-local-exception', [ $secFragment ] );
				unset( $def['help-message'] );
				$def['help'] = $help . $helpMsg->parse();
			}

			$allPrefs[$pref] = $def;
		}
		return $allPrefs;
	}

	/**
	 * @inheritDoc
	 */
	protected function saveFormData( $formData, \PreferencesFormOOUI $form, array $formDescriptor ) {
		if ( !$this->onGlobalPrefsPage( $form ) ) {
			return parent::saveFormData( $formData, $form, $formDescriptor );
		}
		'@phan-var GlobalPreferencesFormOOUI $form';

		$user = $form->getModifiedUser();
		$hiddenPrefs = $this->options->get( 'HiddenPrefs' );
		$result = true;

		// Difference from parent: removed 'editmyprivateinfo'
		if ( !$this->permissionManager->userHasRight( $user, 'editmyoptions' ) ) {
			return Status::newFatal( 'mypreferencesprotected' );
		}

		// Filter input
		$this->applyFilters( $formData, $formDescriptor, 'filterFromForm' );

		// In the parent, we remove 'realname', but this is unnecessary
		// here because GlobalPreferences removes this elsewhere, so
		// the field will not even appear in this form

		// Difference from parent: We are not collecting old user settings

		foreach ( $this->getSaveBlacklist() as $b ) {
			unset( $formData[$b] );
		}

		# If users have saved a value for a preference which has subsequently been disabled
		# via $wgHiddenPrefs, we don't want to destroy that setting in case the preference
		# is subsequently re-enabled
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		foreach ( $hiddenPrefs as $pref ) {
			# If the user has not set a non-default value here, the default will be returned
			# and subsequently discarded
			$formData[$pref] = $userOptionsLookup->getOption( $user, $pref, null, true );
		}

		// Difference from parent: We are ignoring RClimit preference; the parent
		// checks for changes in that preference to update a hidden one, but the
		// RCFilters product is okay with having that be localized

		// We are also not resetting unused preferences in the global context.
		// Otherwise, users could lose data by editing their global preferences
		// on a wiki where some of the preferences don't exist. However, this means
		// that preferences for undeployed extensions or removed code are never
		// removed from the database...

		// Setting the actual preference values:
		$prefs = [];
		foreach ( $formData as $name => $value ) {
			// If this is the '-global' counterpart to a preference.
			if ( self::isGlobalPrefName( $name ) && $value === true ) {
				// Determine the real name of the preference.
				$suffixLen = strlen( self::GLOBAL_EXCEPTION_SUFFIX );
				$realName = substr( $name, 0, -$suffixLen );
				if ( array_key_exists( $realName, $formData ) ) {
					$prefs[$realName] = $formData[$realName];
					if ( $prefs[$realName] === null ) {
						// Special case: null means don't save this row, which can keep the previous value
						$prefs[$realName] = '';
					}
				}
			}
		}

		$matricesToClear = [];
		// Now special processing for CheckMatrices
		foreach ( $this->findCheckMatrices( $formDescriptor ) as $name ) {
			$globalName = $name . self::GLOBAL_EXCEPTION_SUFFIX;
			// Find all separate controls for this CheckMatrix
			$checkMatrix = preg_grep( '/^' . preg_quote( $name ) . '/', array_keys( $formData ) );
			if ( array_key_exists( $globalName, $formData ) && $formData[$globalName] ) {
				// Setting is global, copy the checkmatrices
				foreach ( $checkMatrix as $input ) {
					$prefs[$input] = $formData[$input];
				}
				$prefs[$name] = true;
			} else {
				// Remove all the rows for this CheckMatrix
				foreach ( $checkMatrix as $input ) {
					unset( $prefs[$input] );
				}
				$matricesToClear[] = $name;
			}
			unset( $prefs[$globalName] );
		}
		$this->setGlobalPreferences( $user, $prefs, $form->getContext(), $matricesToClear );

		return $result;
	}

	/**
	 * Finds CheckMatrix inputs in a form descriptor
	 *
	 * @param array $formDescriptor
	 * @return string[] Names of CheckMatrix options (parent only, not sub-checkboxes)
	 */
	private function findCheckMatrices( array $formDescriptor ) {
		$result = [];
		foreach ( $formDescriptor as $name => $info ) {
			if ( ( isset( $info['type'] ) && $info['type'] == 'checkmatrix' ) ||
				 ( isset( $info['class'] ) && $info['class'] == \HTMLCheckMatrix::class )
			) {
				$result[] = $name;
			}
		}

		return $result;
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
			&& !in_array( $info['type'], $this->typesPrevented )
			&& !in_array( $name, $this->disallowedPreferences );

		$isAllowedClass = isset( $info['class'] )
			&& in_array( $info['class'], $this->allowedClasses );

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
	 * @todo Replace with str_ends_with() when PHP 7 support was dropped.
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
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function isUserGlobalized( UserIdentity $user ) {
		return $user->isRegistered() && $this->getUserID( $user ) !== 0;
	}

	/**
	 * Gets the user's ID that we're using in the table
	 * Returns 0 if the user is not global
	 * @param UserIdentity $user
	 * @return int
	 */
	public function getUserID( UserIdentity $user ) {
		$id = $user->getId();
		$cache = $this->getCache();
		return $cache->getWithSetCallback( (string)$id, static function () use ( $user ) {
			$lookup = MediaWikiServices::getInstance()->getCentralIdLookupFactory()->getLookup();
			return $lookup->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW );
		} );
	}

	/**
	 * Get the user's global preferences.
	 * @param UserIdentity $user
	 * @param bool $skipCache Whether the preferences should be loaded strictly from DB
	 * @return false|string[] Array keyed by preference name, or false if not found.
	 */
	public function getGlobalPreferencesValues( UserIdentity $user, $skipCache = false ) {
		$id = $this->getUserID( $user );
		if ( !$id ) {
			$this->logger->warning( "Couldn't find a global ID for user {user}",
				[ 'user' => $user->getName() ]
			);
			return false;
		}
		$storage = $this->makeStorage( $user );
		return $storage->load( $skipCache );
	}

	/**
	 * Save the user's global preferences.
	 * @param User $user
	 * @param array $newGlobalPrefs Array keyed by preference name.
	 * @param IContextSource $context The request context.
	 * @param string[] $checkMatricesToClear List of check matrix controls that
	 *        need their rows purged
	 * @return bool True on success, false if the user isn't global.
	 */
	public function setGlobalPreferences(
		User $user,
		$newGlobalPrefs,
		IContextSource $context,
		array $checkMatricesToClear = []
	) {
		$id = $this->getUserID( $user );
		if ( !$id ) {
			return false;
		}

		// Use a new instance of the current user to fetch the form descriptor because that way
		// we're working with the previous user options and not those that are currently in the
		// process of being saved (we only want the option names here, so don't care what the
		// values are).
		$userForDescriptor = User::newFromId( $user->getId() );

		// Save the global options.
		$storage = $this->makeStorage( $user );
		$knownPrefs = array_keys( $this->getFormDescriptor( $userForDescriptor, $context ) );

		$storage->save( $newGlobalPrefs, $knownPrefs, $checkMatricesToClear );

		$user->clearInstanceCache();
		return true;
	}

	/**
	 * Deletes all of a user's global preferences.
	 * Assumes that the user is globalized.
	 * @param User $user
	 */
	public function resetGlobalUserSettings( User $user ) {
		$this->makeStorage( $user )->delete();
	}

	/**
	 * Convenience function to check if we're on the global prefs page.
	 * @param IContextSource|null $context The context to use; if not set main request context is used.
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
	 * @param IContextSource|null $context The context to use; if not set main request context is used.
	 * @return bool
	 */
	public function onLocalPrefsPage( $context = null ) {
		$context = $context ?: RequestContext::getMain();
		return $context->getTitle()
			&& $context->getTitle()->isSpecial( 'Preferences' );
	}

	/**
	 * Processes local user options before they're saved
	 *
	 * @param UserIdentity $user
	 * @param array &$modifiedOptions
	 * @param array $originalOptions
	 * @throws Exception
	 */
	public function handleLocalPreferencesChange(
		UserIdentity $user,
		array &$modifiedOptions,
		array $originalOptions
	) {
		// nothing to do if autoGlobals is empty
		if ( !$this->autoGlobals ) {
			return;
		}

		$preferencesChanged = false;
		$globals = $this->getGlobalPreferencesValues( $user, true );

		if ( $globals ) {
			// Need this so we can check for newly added options as well as soon-to-be-deleted options
			$mergedOptions = array_merge( $originalOptions, $modifiedOptions );
			foreach ( $mergedOptions as $optName => $optVal ) {
				// Ignore if ends in "-global".
				if ( static::isGlobalPrefName( $optName ) ) {
					unset( $modifiedOptions[ $optName ] );
				}

				$isAutoGlobal = in_array( $optName, $this->autoGlobals );
				$localExceptionName = $optName . static::LOCAL_EXCEPTION_SUFFIX;
				$hasLocalException = isset( $mergedOptions[ $localExceptionName ] )
					&& $mergedOptions[ $localExceptionName ];
				if ( $isAutoGlobal
					&& !$hasLocalException
					&& array_key_exists( $optName, $globals )
				) {
					$globals[$optName] = $optVal;
					$preferencesChanged = true;
				}
			}

			if ( $preferencesChanged ) {
				$this->makeStorage( $user )->save( $globals, array_keys( $globals ) );
			}
		}
	}

	/**
	 * Factory for preference storage
	 *
	 * @param UserIdentity $user
	 * @return Storage
	 */
	protected function makeStorage( UserIdentity $user ) {
		$id = $this->getUserID( $user );
		if ( !$id ) {
			throw new LogicException( 'User not set or is not global on call to ' . __METHOD__ );
		}
		return new Storage( $id );
	}
}
