( function () {
	'use strict';
	/**
	 * Store all OOUI widgets with their reference so we can toggle
	 * them easily on/off.
	 */
	var checkboxesBySection = {},
		checkboxSelectAllBySection = {},
		selectAllCheckboxesOngoing = false;

	/**
	 * Infuse the 'select all' checkbox
	 *
	 * @param {jQuery} $root Section root
	 */
	function infuseSelectAllToHeader( $root ) {
		var checkbox = OO.ui.infuse( $root.find( '.globalprefs-section-select-all' ) ),
			sectionID = $root.prop( 'id' );

		// Store for reference
		checkboxSelectAllBySection[ sectionID ] = checkbox;

		checkbox.on( 'change', function ( isChecked ) {
			// Don't update child widgets if event was triggered by updateSelectAllCheckboxState
			if ( !selectAllCheckboxesOngoing && checkboxesBySection[ sectionID ] ) {
				checkboxesBySection[ sectionID ].forEach( function ( checkboxWidget ) {
					checkboxWidget.setSelected( isChecked );
				} );
			}
		} );
	}

	/**
	 * Update the selected state of a 'check all' checkbox,
	 * based on whether all its associated checkboxes are selected or not.
	 *
	 * @param {string} sectionID Section ID
	 */
	function updateSelectAllCheckboxState( sectionID ) {
		if ( selectAllCheckboxesOngoing ) {
			// Do not change the state of the 'select all' checkbox
			// while we change the selection of the sub-checkboxes
			// in the relevant page on purpose
			return;
		}
		// Suppress event listener
		selectAllCheckboxesOngoing = true;
		checkboxSelectAllBySection[ sectionID ].setSelected(
			// Selection state should be only set if all checkboxes
			// are selected
			checkboxesBySection[ sectionID ] &&
			checkboxesBySection[ sectionID ].every( function ( checkboxWidget ) {
				return checkboxWidget.isSelected();
			} )
		);
		selectAllCheckboxesOngoing = false;
	}

	/**
	 * Initialize the OOUI widgets by infusing all checkboxes and their
	 * related widgets, so we can refer to them later when toggling.
	 */
	$( function () {

		// htmlform.enhance is run when a preference tab is made visible
		mw.hook( 'htmlform.enhance' ).add( function ( $root ) {
			// Add the 'select all' checkbox
			infuseSelectAllToHeader( $root );

			// Go over all checkboxes, assign their matching widgets, and connect to events
			$root.find( '.mw-globalprefs-global-check.oo-ui-checkboxInputWidget' ).each( function () {
				var associatedWidgetOOUI,
					checkbox = OO.ui.infuse( this ),
					sectionID = checkbox.$element.closest( '.oo-ui-layout.oo-ui-tabPanelLayout' ).prop( 'id' ),
					fullName = checkbox.$input.prop( 'name' ),
					// id = checkbox.$input.prop( 'id' ).replace( /[\\"]/g, '\\$&' ),
					// Find the name (without the '-global' suffix, but with the 'wp' prefix).
					prefName = fullName.substr( 0, fullName.length - '-global'.length ).replace( /[\\"]/g, '\\$&' ),
					$associatedWidget = $( ':input[name="' + prefName + '"], :input[name="' + prefName + '[]"]' )
						.closest( '.oo-ui-widget[data-ooui]' );

				try {
					associatedWidgetOOUI = OO.ui.infuse( $associatedWidget );
				} catch ( err ) {
					// If, for whatever reason, we could not find an associated widget,
					// or infuse it, fail gracefully and move to the next iteration
					return true;
				}

				// Store references to all checkboxes in the same section
				checkboxesBySection[ sectionID ] = checkboxesBySection[ sectionID ] || [];
				checkboxesBySection[ sectionID ].push( checkbox );

				// Initialize starting state depending on checkbox state
				associatedWidgetOOUI.setDisabled( !checkbox.isSelected() );
				updateSelectAllCheckboxState( sectionID );
				// Respond to event
				checkbox.on( 'change', function ( isChecked ) {
					associatedWidgetOOUI.setDisabled( !isChecked );

					// Update the 'select all' checkbox for this section
					updateSelectAllCheckboxState( sectionID );
				} );
			} );
		} );
	} );
}() );
