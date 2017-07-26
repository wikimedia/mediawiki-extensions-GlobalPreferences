( function ( mw, $ ) {
	'use strict';

	$( 'input.mw-globalprefs-global-check' ).on( 'change', function () {
		var $labels,

			// Find the name (without the '-global' suffix, but with the 'wp' prefix).
			fullName = $( this ).attr( 'name' ),
			name = fullName.substr( 0, fullName.length - '-global'.length ),

			// Is this preference enabled globally?
			enabled = $( this ).prop( 'checked' ),

			// The table rows relating to this preference
			// (two or three rows, depending on whether there's a help row).
			$globalCheckRow,
			$labelRow,
			$rows;

		// All the labels for this preference (not all have for='').
		$labels = $( 'label[for^=\'mw-input-' + name + '\']' )
			.closest( 'tr' )
			.find( 'label' )
			.not( '[for$=\'-global\']' );

		// Disable or enable the related preferences inputs.
		$( ':input[name=\'' + name + '\']' ).prop( 'disabled', !enabled );
		if ( enabled ) {
			$labels.removeClass( 'globalprefs-disabled' );
		} else {
			$labels.addClass( 'globalprefs-disabled' );
		}

		// Collect the related rows. The latter two in the $rows array will often be the same element.
		$globalCheckRow = $( this ).closest( 'tr' );
		$labelRow = $labels.closest( 'tr' );
		$rows = $( [
			$labelRow[ 0 ],
			$labelRow.next()[ 0 ],
			$globalCheckRow[ 0 ]
		] );

		// Add a class on hover, to highlight the related rows.
		$( this ).add( 'label[for=\'' + $( this ).attr( 'id' ) + '\']' ).hover( function () {
			// Hover on.
			$rows.addClass( 'globalprefs-hover' );
		}, function () {
			// Hover off.
			$rows.removeClass( 'globalprefs-hover' );
		} );

	} ).change();

}( mediaWiki, jQuery ) );
