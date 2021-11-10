<?php

namespace GlobalPreferences;

use PreferencesFormOOUI;
use Xml;

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
