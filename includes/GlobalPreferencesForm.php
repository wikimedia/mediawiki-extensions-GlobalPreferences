<?php

namespace GlobalPreferences;

use Html;
use IContextSource;
use MediaWiki\MediaWikiServices;
use PreferencesForm;

/**
 * The GlobalPreferencesForm changes the display format, and adds section headers linking back to
 * the local-preferences form.
 *
 * @package GlobalPreferences
 */
class GlobalPreferencesForm extends PreferencesForm {

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
	 * Get the whole body of the form, adding the global preferences header text to the top of each
	 * section. Javascript will later add the 'select all' checkbox to this header.
	 * @return string
	 */
	function getBody() {
		// Load global values for any preferences with local exceptions.
		/** @var GlobalPreferencesFactory $globalPreferences */
		$globalPreferences = MediaWikiServices::getInstance()->getPreferencesFactory();
		$globalPreferences->setUser( $this->getUser() );
		$globalPrefValues = $globalPreferences->getGlobalPreferencesValues();
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
				$this->mFieldData[ $fieldName ] = $globalPrefValues[ $fieldName ];
			}
		}

		// Add help text to the top of every section.
		foreach ( $this->getPreferenceSections() as $section ) {
			$colHeaderText = Html::element(
				'span',
				[ 'class' => 'col-header' ],
				$this->getMessage( 'tooltip-globalprefs-check-label' )
			);
			$secHeader = Html::rawElement(
				'div',
				[ 'class' => 'globalprefs-section-header' ],
				$colHeaderText
			);
			$this->addHeaderText( $secHeader, $section );
		}

		return parent::getBody();
	}
}
