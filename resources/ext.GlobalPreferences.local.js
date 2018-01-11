( function ( mw, $, OO ) {
	'use strict';

	/**
	 * Enable and disable the related preference field when selecting the local exception checkbox.
	 */
	$( ':input.mw-globalprefs-local-exception' ).on( 'change', function () {
		var localExName, prefName, enabled, $prefInput, oouiWidget;
		// Figure out what the preference name is by stripping the local exception suffix.
		localExName = $( this ).attr( 'name' );
		prefName = localExName.substr( 0, localExName.length - '-local-exception'.length );
		enabled = $( this ).prop( 'checked' );
		$prefInput = $( ':input[name="' + prefName + '"]' );

		if ( $prefInput.parent( '.oo-ui-widget' ).length > 0 ) {
			// First see if this is a OOUI field.
			oouiWidget = OO.ui.infuse( $prefInput.parent( '.oo-ui-widget' ) );
			oouiWidget.setDisabled( !enabled );

		} else {
			// Otherwise treat it as a normal form input.
			$prefInput.prop( 'disabled', !enabled );
		}
	} );

}( mediaWiki, jQuery, OO ) );
