( function ( mw, $ ) {
	'use strict';

	/**
	 * When one of the global checkboxes is changed enable or disable its matching preference.
	 * Also highlight the relevant preference when hovering on the checkbox.
	 */
	function onChangeGlobalCheckboxes() {
		var $labels, $inputs,

			// Find the name (without the '-global' suffix, but with the 'wp' prefix).
			fullName = $( this ).attr( 'name' ),
			name = fullName.substr( 0, fullName.length - '-global'.length ).replace( /[\\"]/g, '\\$&' ),
			id = $( this ).attr( 'id' ).replace( /[\\"]/g, '\\$&' ),

			// Is this preference enabled globally?
			enabled = $( this ).prop( 'checked' ),

			// This selector is required because there's no common class on these.
			fieldSelector = '[class^="mw-htmlform-field-"]:not([class*="oo-ui-widget"])',

			// The form 'rows' (which are adjacent divs) relating to this preference
			// (two or three rows, depending on whether there's a help row, all contained in $rows).
			$globalCheckRow,
			$mainFieldRow,
			$rows;

		// The current preference's inputs (can be multiple, and not all will have the same name).
		$inputs = $( ':input[name="' + name + '"], :input[name="' + name + '[]"]' )
			.parents( '.mw-input' )
			.find( ':input[name]' )
			.not( '.checkmatrix-forced' );

		// All the labels for this preference (not all have for='' nor are even labels).
		$labels = $inputs
			.closest( fieldSelector )
			.find( 'label, td' )
			.not( '[for$="-global"]' );

		// Collect the related rows. The main field row is sometimes followed by a help-tip row.
		$globalCheckRow = $( this ).closest( fieldSelector );
		$mainFieldRow = $labels.closest( fieldSelector );
		$rows = $().add( $globalCheckRow ).add( $mainFieldRow );
		if ( $mainFieldRow.next().hasClass( 'htmlform-tip' ) ) {
			$rows = $rows.add( $mainFieldRow.next() );
		}

		// Disable or enable the related preferences inputs.
		$inputs.each( function () {
			var widget, $widgetElement;
			$widgetElement = $( this ).parents( '.oo-ui-widget' );
			if ( $widgetElement.length === 1 ) {
				// OOUI widget.
				widget = OO.ui.infuse( $widgetElement );
				widget.setDisabled( !enabled );
			} else {
				// Normal form input.
				$inputs.prop( 'disabled', !enabled );
			}
		} );
		// Mark the relevant input's labels to match (only required for non-OOUI).
		if ( enabled ) {
			$labels.removeClass( 'globalprefs-disabled' );
		} else {
			$labels.addClass( 'globalprefs-disabled' );
		}

		// Add a class on hover, to highlight the related rows.
		$( this ).add( 'label[for="' + id + '"]' ).on( {
			mouseenter: function () {
				$rows.addClass( 'globalprefs-hover' );
			},
			mouseleave: function () {
				$rows.removeClass( 'globalprefs-hover' );
			}
		} );
	}

	/**
	 * Add select all behaviour to a group of checkboxes.
	 * @param {jQuery} $selectAll The select-all checkbox.
	 * @param {jQuery} $targets The target checkboxes.
	 */
	function selectAllCheckboxes( $selectAll, $targets ) {
		// Handle the select-all box changing.
		$selectAll.on( 'change', function () {
			$targets.prop( 'checked', $( this ).prop( 'checked' ) ).change();
		} );
		// Handle any of the targets changing.
		$targets.on( 'change', function () {
			var allSelected = true;
			$targets.each( function () {
				allSelected = allSelected && $( this ).prop( 'checked' );
			} );
			$selectAll.prop( 'checked', allSelected );
		} );
	}

	/**
	 * Add the 'select all' checkbox to the form section headers.
	 */
	function addSelectAllToHeader() {
		// For each preferences form tab, add a select-all checkbox to the header.
		$( '.globalprefs-section-header' ).each( function () {
			var selectAll = mw.message( 'globalprefs-select-all' ).escaped(),
				$checkbox,
				$allGlobalCheckboxes;

			// Add the checkbox. Its label is already present, so we just need to update the label tooltip.
			$checkbox = $( '<input>', { id: 'globalprefs-select-all', type: 'checkbox', title: selectAll } );
			$( this ).prepend( $checkbox );
			$( this ).find( 'label' ).attr( 'title', selectAll );

			// Determine all the matching checkboxes.
			$allGlobalCheckboxes = $( this ).parent( 'fieldset' ).find( '.mw-globalprefs-global-check:checkbox' );

			// Enable the select-all behaviour.
			selectAllCheckboxes( $checkbox, $allGlobalCheckboxes );
		} );
	}

	// Activate the above functions.
	$( document ).ready( function () {
		addSelectAllToHeader();
		$( 'input.mw-globalprefs-global-check' ).on( 'change', onChangeGlobalCheckboxes ).change();
	} );
}( mediaWiki, jQuery ) );
