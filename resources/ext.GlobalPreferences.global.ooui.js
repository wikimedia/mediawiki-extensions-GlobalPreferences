( function () {
	'use strict';
	/**
	 * Store all OOUI widgets with their reference so we can toggle
	 * them easily on/off.
	 */
	var widgets = {},
		checkboxesBySection = {},
		checkboxSelectAllBySection = {},
		selectAllCheckboxesOngoing = false;

	/**
	 * Add the 'select all' checkbox to the form section headers.
	 */
	function addSelectAllToHeader() {
		// For each preferences form tab, add a select-all checkbox to the header.
		$( '.globalprefs-section-header' ).each( function () {
			var selectAll = mw.message( 'globalprefs-select-all' ).text(),
				// Add the checkbox. Its label is already present,
				// so we just need to update the label tooltip.
				checkbox = new OO.ui.CheckboxInputWidget( {
					inputId: 'globalprefs-select-all',
					title: selectAll
				} ),
				sectionID = $( this ).closest( '.oo-ui-layout.oo-ui-tabPanelLayout' ).prop( 'id' );

			$( this ).prepend( checkbox.$element );

			// Store for reference
			checkboxSelectAllBySection[ sectionID ] = checkbox;

			// Connect to change event to toggle all checkboxes in the section
			// HACK: We want to separate the 'change' event
			// of the 'select all' checkbox to two:
			// - The case of a user actively clicking
			//   it - in which case all sub checkboxes should change their states
			// - The case where the state of all associated checkboxes are the same
			//   or not the same, and the 'select all' checked state changes to
			//   reflect that, but in that case, it should not trigger changes todo
			//   its associated checkboxes.
			// We use a global variable that changes to 'true' when the mousedown
			// event is triggered to state specifically that this represent user-click
			// rather than automated state change.
			// TODO: This should be upstreamed into OOUI's CheckboxInputWidget's behavior.
			checkbox.$element.on( 'mousedown', function () {
				selectAllCheckboxesOngoing = true;
			} );
			checkbox.on( 'change', function ( isChecked ) {
				var sectionID = checkbox.$element.closest( '.oo-ui-layout.oo-ui-tabPanelLayout' ).prop( 'id' );
				if ( selectAllCheckboxesOngoing && checkboxesBySection[ sectionID ] ) {
					checkboxesBySection[ sectionID ].forEach( function ( checkboxWidget ) {
						checkboxWidget.setSelected( isChecked );
					} );
					selectAllCheckboxesOngoing = false;
				}
			} );
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
		checkboxSelectAllBySection[ sectionID ].setSelected(
			// Selection state should be only set if all checkboxes
			// are selected
			checkboxesBySection[ sectionID ] &&
			checkboxesBySection[ sectionID ].every( function ( checkboxWidget ) {
				return checkboxWidget.isSelected();
			} )
		);
	}

	/**
	 * Initialize the OOUI widgets by infusing all checkboxes and their
	 * related widgets, so we can refer to them later when toggling.
	 */
	function initialize() {
		// Go over all checkboxes, assign their matching widgets, and connect to events
		$( '.mw-globalprefs-global-check.oo-ui-checkboxInputWidget' ).each( function () {
			var associatedWidgetOOUI,
				checkbox = OO.ui.infuse( $( this ) ),
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

			// Store references to associated widgets
			widgets[ prefName ] = associatedWidgetOOUI;
			// Store references to all checkboxes in the same section
			checkboxesBySection[ sectionID ] = checkboxesBySection[ sectionID ] || [];
			checkboxesBySection[ sectionID ].push( checkbox );

			// Initialize starting state depending on checkbox state
			widgets[ prefName ].setDisabled( !checkbox.isSelected() );
			// Respond to event
			checkbox.on( 'change', function ( isChecked ) {
				var fullName = checkbox.$input.prop( 'name' ),
					prefName = fullName.substr( 0, fullName.length - '-global'.length ).replace( /[\\"]/g, '\\$&' ),
					sectionID = checkbox.$element.closest( '.oo-ui-layout.oo-ui-tabPanelLayout' ).prop( 'id' );

				widgets[ prefName ].setDisabled( !isChecked );

				// Update the 'select all' checkbox for this section
				updateSelectAllCheckboxState( sectionID );
			} );
		} );

		// Add the 'select all' checkbox
		addSelectAllToHeader();

		// Update all 'select all' checkbox initial states
		Object.keys( checkboxSelectAllBySection ).forEach( function ( sectionID ) {
			updateSelectAllCheckboxState( sectionID );
		} );
	}

	// Activate the above functions.
	$( document ).ready( function () {
		initialize();
	} );

}() );
