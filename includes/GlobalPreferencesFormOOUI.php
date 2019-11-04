<?php

namespace GlobalPreferences;

use MediaWiki\MediaWikiServices;
use PreferencesFormOOUI;
use User;
use Xml;

/**
 * The GlobalPreferencesForm changes the display format, and adds section headers linking back to
 * the local-preferences form.
 *
 * @package GlobalPreferences
 */
class GlobalPreferencesFormOOUI extends PreferencesFormOOUI {

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
	 * Get the whole body of the form, adding the global preferences header text to the top of each
	 * section. JavaScript will later add the 'select all' checkbox to this header.
	 * @return string
	 */
	public function getBody() {
		// Load global values for any preferences with local exceptions.
		/** @var GlobalPreferencesFactory $globalPreferences */
		$globalPreferences = MediaWikiServices::getInstance()->getPreferencesFactory();
		'@phan-var GlobalPreferencesFactory $globalPreferences';
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
				// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
				$this->mFieldData[$fieldName] = $globalPrefDefinitions[$fieldName]['default'];
			}
		}

		// Add checbox to the top of every section.
		foreach ( $this->getPreferenceSections() as $section ) {
			$colHeaderText = $this->getMessage( 'globalprefs-col-header' )->text();
			$secHeader = new \OOUI\FieldLayout(
				new \OOUI\CheckboxInputWidget( [
					'infusable' => true,
					'classes' => [ 'globalprefs-section-select-all' ],
				] ),
				[
					'align' => 'inline',
					'label' => $colHeaderText,
					'classes' => [ 'globalprefs-section-header' ],
				]
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
		if ( !$this->areOptionsEditable() && !$this->isPrivateInfoEditable() ) {
			return '';
		}

		// HACK: We need the buttons from OOUIHTMLForm but we don't
		// want the button given by the direct parent (PreferencesFormOOUI)
		// because the parent creates a "reset" button that is meant specifically
		// for resetting *local* preferences, and we need that button
		// in the context of global preferences (with a different URL).
		// We're going to call the grandparent directly and skip the parent
		$html = \OOUIHTMLForm::getButtons();

		if ( $this->areOptionsEditable() ) {
			$t = $this->getTitle()->getSubpage( 'reset' );

			$html .= new \OOUI\ButtonWidget( [
				'infusable' => true,
				'id' => 'mw-prefs-restoreprefs',
				'label' => $this->msg( 'globalprefs-restoreprefs' )->text(),
				'href' => $t->getLinkURL(),
				'flags' => [ 'destructive' ],
				'framed' => false,
			] );

			$html = Xml::tags( 'div', [ 'class' => 'mw-prefs-buttons' ], $html );
		}

		return $html;
	}
}
