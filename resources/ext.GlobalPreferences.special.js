( function ( mw, $ ) {
	'use strict';

	/**
	 * When one of the global checkboxes is changed enable or disable its matching preference.
	 * Also highlight the relevant preference when hovering on the checkbox.
	 */
	function onChangeGlobalCheckboxes() {
		var $labels,

			// Find the name (without the '-global' suffix, but with the 'wp' prefix).
			fullName = $( this ).attr( 'name' ),
			name = fullName.substr( 0, fullName.length - '-global'.length ),

			// Is this preference enabled globally?
			enabled = $( this ).prop( 'checked' ),

			// This selector is required because there's no common class on these.
			fieldSelector = '[class^="mw-htmlform-field-"]',

			// The form 'rows' (which are adjacent divs) relating to this preference
			// (two or three rows, depending on whether there's a help row, all contained in $rows).
			$globalCheckRow,
			$mainFieldRow,
			$rows,

			// The current preference's inputs (can be multiple, and not all will have the same name).
			$inputs = $( ':input[name="' + name + '"], :input[name="' + name + '[]"]' )
				.parents( '.mw-input' )
				.find( ':input' )
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
		$inputs.prop( 'disabled', !enabled );
		if ( enabled ) {
			$labels.removeClass( 'globalprefs-disabled' );
		} else {
			$labels.addClass( 'globalprefs-disabled' );
		}

		// Add a class on hover, to highlight the related rows.
		$( this ).add( 'label[for="' + $( this ).attr( 'id' ) + '"]' ).on( {
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
			var selectAll = mw.message( 'globalprefs-select-all' ),
				$checkbox,
				$allGlobalCheckboxes;
			// Wrap the checkbox in a fieldset so it acts/looks the same as all the global checkboxes.
			$checkbox = $( '<fieldset class="ext-globalpreferences-select-all"><label><input type="checkbox" /> ' + selectAll + '</label></fieldset>' );
			$( this ).append( $checkbox );

			// Determine all the matching checkboxes.
			$allGlobalCheckboxes = $( this ).parent( 'fieldset' ).find( '.mw-globalprefs-global-check:checkbox' );

			// Enable the select-all behaviour.
			selectAllCheckboxes( $checkbox.find( ':checkbox' ), $allGlobalCheckboxes );
		} );
	}

	// Activate the above functions.
	addSelectAllToHeader();
	$( 'input.mw-globalprefs-global-check' ).on( 'change', onChangeGlobalCheckboxes ).change();
}( mediaWiki, jQuery ) );
