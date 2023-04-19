<?php

namespace GlobalPreferences;

use PreferencesFormOOUI;

/**
 * The GlobalPreferencesForm changes the display format, and adds section headers linking back to
 * the local-preferences form.
 *
 * @package GlobalPreferences
 */
class GlobalPreferencesFormOOUI extends PreferencesFormOOUI {

	/**
	 * Get the whole body of the form, adding the global preferences header text to the top of each
	 * section. JavaScript will later add the 'select all' checkbox to this header.
	 * @return string
	 */
	public function getBody() {
		// Add checkbox to the top of every section.
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
			$this->addHeaderHtml( $secHeader, $section );
		}

		return parent::getBody();
	}

}
