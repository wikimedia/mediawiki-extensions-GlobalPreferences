( function ( mw, $, OO ) {
	'use strict';

	/**
	 * Enable and disable the related preference field when selecting the local exception checkbox.
	 */
	$( ':input.mw-globalprefs-local-exception' ).on( 'change', function () {
		var localExName, prefName, enabled, $prefInput, oouiWidget;
		// Figure out what the preference name is by stripping the local exception suffix.
		localExName = $( this ).attr( 'name' );
		prefName = localExName.substr( 0, localExName.length - '-local-exception'.length )
			.replace( /[\\"]/g, '\\$&' );
		enabled = $( this ).prop( 'checked' );
		$prefInput = $( ':input[name="' + prefName + '"], :input[name="' + prefName + '[]"]' )
			.parents( '.mw-input' )
			.find( ':input' )
			.not( '.checkmatrix-forced' );

		if ( $prefInput.parent( '.oo-ui-widget' ).length > 0 ) {
			// First see if this is a OOUI field.
			oouiWidget = OO.ui.infuse( $prefInput.parent( '.oo-ui-widget' ) );
			oouiWidget.setDisabled( !enabled );

		} else {
			// Otherwise treat it as a normal form input.
			$prefInput.prop( 'disabled', !enabled );
		}
	} );

	/**
	 * Highlight related preference fields when hovering on the local-exception checkbox.
	 */
	$( '.mw-globalprefs-local-exception input, .mw-globalprefs-local-exception label' ).on( {
		mouseenter: function () {
			var $localExRow,
				$rows = $();
			// Find this table row and the previous one.
			$localExRow = $( this ).parents( 'tr' );
			$rows = $rows.add( $localExRow ).add( $localExRow.prev() );
			// If the previous row isn't a 'mw-htmlform-field-*' then it must be a help text row,
			// and we also want the row before it.
			if ( $localExRow.prev( 'tr[class^="mw-htmlform-field-"]' ).length === 0 ) {
				$rows = $rows.add( $localExRow.prev().prev() );
			}
			// Add a class to all of these rows.
			$rows.addClass( 'mw-globalprefs-hover' );
		},
		mouseleave: function () {
			// Remove hover class from everywhere.
			$( this ).parents( 'table' ).find( 'tr' ).removeClass( 'mw-globalprefs-hover' );
		}
	} );

}( mediaWiki, jQuery, OO ) );
