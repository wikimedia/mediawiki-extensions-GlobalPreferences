<?php

namespace GlobalPreferences;

use Html;
use HTMLForm;
use HTMLFormField;
use IContextSource;
use MediaWiki\MediaWikiServices;
use PreferencesFormLegacy;
use User;
use Xml;

/**
 * The GlobalPreferencesForm changes the display format, and adds section headers linking back to
 * the local-preferences form.
 *
 * @package GlobalPreferences
 */
class GlobalPreferencesForm extends PreferencesFormLegacy {

	/**
	 * Flag that we're in the process of getting global preferences ONLY, i.e. we want to ignore
	 * local exceptions. This is used when we need to overwrite the values of the
	 * GlobalPreferences form and not display the local exception values.
	 * @var bool
	 */
	protected static $gettingGlobalOnly = false;

	/**
	 * Get the value of static::$gettingGlobalOnly (see there for why).
	 * @return bool
	 */
	public static function gettingGlobalOnly() {
		return static::$gettingGlobalOnly;
	}

	/**
	 * Build a new GlobalPreferencesForm from an array of field attributes, and force it to be
	 * have a 'div' display format.
	 *
	 * @param array $descriptor Array of Field constructs, as described above.
	 * @param IContextSource $context The context of the form.
	 * @param string $messagePrefix A prefix to go in front of default messages.
	 */
	public function __construct( $descriptor, IContextSource $context = null, $messagePrefix = '' ) {
		parent::__construct( $descriptor, $context, $messagePrefix );
		$this->setDisplayFormat( 'div' );
	}

	/**
	 * Override this in order to hide empty labels.
	 * @param array[]|HTMLFormField[] $fields Array of fields (either arrays or objects).
	 * @param string $sectionName Identifier for this section.
	 * @param string $fieldsetIDPrefix Prefix for the fieldset of each subsection.
	 * @param bool &$hasUserVisibleFields Whether the section had user-visible fields.
	 * @return string
	 */
	public function displaySection(
		$fields, $sectionName = '', $fieldsetIDPrefix = '', &$hasUserVisibleFields = false
	) {
		foreach ( $fields as $key => $value ) {
			if ( $value instanceof HTMLFormField ) {
				$value->setShowEmptyLabel( false );
			}
		}
		return parent::displaySection(
			$fields, $sectionName, $fieldsetIDPrefix, $hasUserVisibleFields
		);
	}

	/**
	 * Get the whole body of the form, adding the global preferences header text to the top of each
	 * section. Javascript will later add the 'select all' checkbox to this header.
	 * @return string
	 */
	public function getBody() {
		// Load global values for any preferences with local exceptions.
		/** @var GlobalPreferencesFactory $globalPreferences */
		$globalPreferences = MediaWikiServices::getInstance()->getPreferencesFactory();
		$globalPreferences->setUser( $this->getUser() );
		$globalPrefValues = $globalPreferences->getGlobalPreferencesValues( Storage::SKIP_CACHE );

		// Fetch a set of global-only preferences with which we can populate the form,
		// but none of which will actually be in effect (i.e. when viewing the global form, all
		// local exceptions should be in use, but the global values are the ones shown).
		static::$gettingGlobalOnly = true;
		$globalOnlyUser = User::newFromId( $this->getUser()->getId() );
		$globalPrefDefinitions = $globalPreferences->getFormDescriptor(
			$globalOnlyUser,
			$this->getContext()
		);
		static::$gettingGlobalOnly = false;

		// Manually set global pref fields to their global values if they have a local exception.
		foreach ( $this->mFlatFields as $fieldName => $field ) {
			// Ignore this if it's a global or a local-exception preference.
			$isGlobal = GlobalPreferencesFactory::isGlobalPrefName( $fieldName );
			$isLocalException = GlobalPreferencesFactory::isLocalPrefName( $fieldName );
			if ( $isGlobal || $isLocalException ) {
				continue;
			}
			// See if it's got a local exception. It should also always then have a global value,
			// but we check anyway just to be sure.
			$localExceptionName = $fieldName . GlobalPreferencesFactory::LOCAL_EXCEPTION_SUFFIX;
			$hasGlobalValue = isset( $globalPrefValues[ $fieldName ] );
			if ( $this->getUser()->getOption( $localExceptionName ) && $hasGlobalValue ) {
				// And if it does, use the global value.
				$this->mFieldData[$fieldName] = $globalPrefDefinitions[$fieldName]['default'];
			}
		}

		// Add help text to the top of every section. This creates form components because the
		// select-all checkbox will be added here by JS in the ext.GlobalPreferencs.global module.
		foreach ( $this->getPreferenceSections() as $section ) {
			$colHeaderText = $this->getMessage( 'globalprefs-col-header' )->text();
			$secHeader = Html::rawElement(
				'fieldset',
				[ 'class' => 'globalprefs-section-header' ],
				Html::label( $colHeaderText, 'globalprefs-select-all' )
			);
			$this->addHeaderText( $secHeader, $section );
		}

		return parent::getBody();
	}

	/**
	 * Override (and duplicate most of) PreferencesForm::getButtons() in order to change the
	 * reset-link message.
	 * @return string
	 */
	public function getButtons() {
		$attrs = [ 'id' => 'mw-prefs-restoreprefs' ];

		if ( !$this->getModifiedUser()->isAllowedAny( 'editmyprivateinfo', 'editmyoptions' ) ) {
			return '';
		}

		// Use grand-parent's getButtons().
		$html = HTMLForm::getButtons();

		if ( $this->getModifiedUser()->isAllowed( 'editmyoptions' ) ) {
			$t = $this->getTitle()->getSubpage( 'reset' );

			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			$html .= "\n" . $linkRenderer->makeLink(
				$t,
				$this->msg( 'globalprefs-restoreprefs' )->text(),
				Html::buttonAttributes( $attrs, [ 'mw-ui-quiet' ] )
			);

			$html = Xml::tags( 'div', [ 'class' => 'mw-prefs-buttons' ], $html );
		}

		return $html;
	}
}
