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

use LogicException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\Field\HTMLCheckMatrix;
use MediaWiki\HTMLForm\Field\HTMLSelectOrOtherField;
use MediaWiki\Language\RawMessage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Preferences\DefaultPreferencesFactory;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use OOUI\ButtonWidget;
use RuntimeException;

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
		// Checking old alias for compatibility with unchanged extensions
		\HTMLSelectOrOtherField::class,
		HTMLSelectOrOtherField::class,
		\MediaWiki\Extension\BetaFeatures\HTMLFeatureField::class,
		// Checking old alias for compatibility with unchanged extensions
		\HTMLCheckMatrix::class,
		HTMLCheckMatrix::class,
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
				throw new RuntimeException(
					"Attempted to load global preferences page for {$user->getName()} whose "
					. 'preference values failed to load'
				);
			}
			return $this->getPreferencesGlobal( $user, $preferences, $globalPrefs, $context );
		}
		return $this->getPreferencesLocal( $user, $preferences, $globalPrefNames, $context );
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
			// If this is not globalizable, or hasn't been set globally.
			if ( !isset( $def['section'] )
				|| !in_array( $name, $globalPrefNames )
				|| !$this->isGlobalizablePreference( $name, $def )
			) {
				continue;
			}
			$localExName = $name . UserOptionsLookup::LOCAL_EXCEPTION_SUFFIX;
			$localExValueUser = $userOptionsLookup->getBoolOption( $user, $localExName );

			// Add a new local exception preference after this one.
			$cssClasses = [
				'mw-globalprefs-local-exception',
				'mw-globalprefs-local-exception-for-' . $name,
				'mw-prefs-search-noindex',
			];
			$section = $def['section'];
			$secFragment = static::getSectionFragmentId( $section );
			$labelMsg = $context->msg( 'globalprefs-set-local-exception', [ $secFragment ] );
			$modifiedPrefs[$localExName] = [
				'type' => 'toggle',
				'label-raw' => $labelMsg->parse(),
				'default' => $localExValueUser,
				'section' => $section,
				'cssclass' => implode( ' ', $cssClasses ),
				'hide-if' => $def['hide-if'] ?? false,
				'disable-if' => $def['disable-if'] ?? false,
			];
			if ( isset( $def['disable-if'] ) ) {
				$modifiedPrefs[$name]['disable-if'] = [ 'OR', $def['disable-if'],
					[ '!==', $localExName, '1' ]
				];
			} else {
				$modifiedPrefs[$name]['disable-if'] = [ '!==', $localExName, '1' ];
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
		$allPrefs = [];
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();

		// Add the "Restore all default preferences" link like on Special:Preferences
		// (the normal preference entry is not globalizable)
		$allPrefs['restoreprefs'] = [
			'type' => 'info',
			'raw' => true,
			'label-message' => 'prefs-user-restoreprefs-label',
			'default' => Html::element(
				'a',
				[
					'href' => SpecialPage::getTitleFor( 'GlobalPreferences' )
						->getSubpage( 'reset' )->getLocalURL()
				],
				$context->msg( 'globalprefs-restoreprefs' )->text()
			),
			'section' => 'personal/info',
		];

		// Add all corresponding new global fields.
		foreach ( $preferences as $pref => $def ) {
			// Ignore unwanted preferences.
			if ( !$this->isGlobalizablePreference( $pref, $def ) ) {
				continue;
			}
			// If a 'info' preference was allowed (i.e. 'canglobal' is set to true), then we should not add a checkbox
			// as it doesn't make sense.
			if ( isset( $def['type'] ) && $def['type'] === 'info' ) {
				$allPrefs[$pref] = $def;
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
				'disable-if' => $def['disable-if'] ?? false,
			];
			if ( isset( $def['disable-if'] ) ) {
				$def['disable-if'] = [ 'OR', $def['disable-if'],
					[ '!==', $pref . static::GLOBAL_EXCEPTION_SUFFIX, '1' ]
				];
			} else {
				$def['disable-if'] = [ '!==', $pref . static::GLOBAL_EXCEPTION_SUFFIX, '1' ];
			}
			// If this has a local exception, override it and append a help message to say so.
			if ( $isGlobal
				&& $userOptionsLookup->getBoolOption( $user, $pref . UserOptionsLookup::LOCAL_EXCEPTION_SUFFIX )
			) {
				$def['default'] = $this->getOptionFromUser( $pref, $def, $globalPrefs );
				// Create a link to the relevant section of GlobalPreferences.
				$secFragment = static::getSectionFragmentId( $def['section'] );
				$helpMsg = [ 'globalprefs-has-local-exception', $secFragment ];
				// Merge the help messages.
				if ( isset( $def['help'] ) ) {
					$def['help-messages'] = [ new RawMessage( $def['help'] . '<br />' ), $helpMsg ];
					unset( $def['help'] );
				} elseif ( isset( $def['help-message'] ) ) {
					$def['help-messages'] = [ $def['help-message'], new RawMessage( '<br />' ), $helpMsg ];
					unset( $def['help-message'] );
				} elseif ( isset( $def['help-messages'] ) ) {
					$def['help-messages'][] = new RawMessage( '<br />' );
					$def['help-messages'][] = $helpMsg;
				} else {
					$def['help-message'] = $helpMsg;
				}
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
		$hiddenPrefs = $this->options->get( 'HiddenPrefs' );
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
		$suffixLen = strlen( self::GLOBAL_EXCEPTION_SUFFIX );
		foreach ( $formData as $name => $value ) {
			// If this is the '-global' counterpart to a preference.
			if ( self::isGlobalPrefName( $name ) && $value === true ) {
				// Determine the real name of the preference.
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

		return true;
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
				// Checking old alias for compatibility with unchanged extensions
				( isset( $info['class'] ) && $info['class'] == \HTMLCheckMatrix::class ) ||
				( isset( $info['class'] ) && $info['class'] == HTMLCheckMatrix::class )
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

		// Allow explicitly define if a preference can be globalized,
		// especially useful for custom field classes.
		// TODO: Deprecate 'noglobal' in favour of this param.
		if ( isset( $info['canglobal'] ) ) {
			return (bool)$info['canglobal'];
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
		return str_ends_with( $name, static::GLOBAL_EXCEPTION_SUFFIX );
	}

	/**
	 * A convenience function to check if a preference name is for a local-exception preference.
	 * @param string $name The name to check.
	 * @return bool
	 */
	public static function isLocalPrefName( $name ) {
		return str_ends_with( $name, UserOptionsLookup::LOCAL_EXCEPTION_SUFFIX );
	}

	/**
	 * Checks if the user is globalized.
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function isUserGlobalized( UserIdentity $user ) {
		$utils = MediaWikiServices::getInstance()->getUserIdentityUtils();
		return $utils->isNamed( $user ) && $this->getUserID( $user ) !== 0;
	}

	/**
	 * Gets the user's ID that we're using in the table
	 * Returns 0 if the user is not global
	 * @param UserIdentity $user
	 * @return int
	 */
	public function getUserID( UserIdentity $user ) {
		$lookup = MediaWikiServices::getInstance()->getCentralIdLookup();
		return $lookup->isOwned( $user ) ?
			$lookup->centralIdFromName( $user->getName(), CentralIdLookup::AUDIENCE_RAW ) :
			0;
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
		$userForDescriptor = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $user->getId() );

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
	 * Convenience function to check if we're on the local prefs page.
	 *
	 * @param IContextSource|null $context The context to use; if not set main request context is used.
	 * @return bool
	 */
	public function onLocalPrefsPage( $context = null ) {
		$context = $context ?: RequestContext::getMain();
		return $context->getTitle() && $context->getTitle()->isSpecial( 'Preferences' );
	}

	/**
	 * Processes local user options before they're saved
	 *
	 * @param UserIdentity $user
	 * @param array &$modifiedOptions
	 * @param array $originalOptions
	 */
	public function handleLocalPreferencesChange(
		UserIdentity $user,
		array &$modifiedOptions,
		array $originalOptions
	) {
		$shouldModify = [];
		$mergedOptions = array_merge( $originalOptions, $modifiedOptions );
		foreach ( $this->autoGlobals as $optName ) {
			// $modifiedOptions can contains options that not actually modified, filter out them
			if ( array_key_exists( $optName, $modifiedOptions ) &&
				( !array_key_exists( $optName, $originalOptions ) ||
				$modifiedOptions[$optName] !== $originalOptions[$optName] ) &&
				// And skip options that have local exceptions
				!( $mergedOptions[$optName . UserOptionsLookup::LOCAL_EXCEPTION_SUFFIX] ?? false )
			) {
				$shouldModify[$optName] = $modifiedOptions[$optName];
			}
		}
		// No auto-global options are modified
		if ( !$shouldModify ) {
			return;
		}

		$preferencesChanged = false;
		$globals = $this->getGlobalPreferencesValues( $user, true );

		if ( $globals ) {
			foreach ( $shouldModify as $optName => $optVal ) {
				if ( array_key_exists( $optName, $globals ) ) {
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
