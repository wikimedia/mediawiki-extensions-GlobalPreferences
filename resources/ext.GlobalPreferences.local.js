( function () {
	'use strict';
	/**
	 * Updates preference input based if user wants to set a local exception.
	 *
	 * @param {string} name Name of the preference
	 * @param {boolean} checked Whether local exception is checked
	 */
	function updatePrefInput( name, checked ) {
		var localExName, prefName, enabled, $prefInput;
		// Figure out what the preference name is by stripping the local exception suffix.
		localExName = name;
		prefName = localExName.substr( 0, localExName.length - '-local-exception'.length )
			.replace( /[\\"]/g, '\\$&' );
		enabled = checked;
		// eslint-disable-next-line no-jquery/no-sizzle
		$prefInput = $( ':input[name="' + prefName + '"], :input[name="' + prefName + '[]"]' );

		if ( $prefInput.parents( '.oo-ui-fieldLayout[data-ooui]' ).length > 0 ) {
			// OOUI widget.
			var $prefLayout = $prefInput.parents( '.oo-ui-fieldLayout[data-ooui]' ).first();
			OO.ui.infuse( $prefLayout ).fieldWidget.setDisabled( !enabled );
		} else {
			// Old-style inputs.
			// @todo is this still needed?
			// eslint-disable-next-line no-jquery/no-sizzle
			$prefInput
				.parents( '.mw-input' )
				.find( ':input' )
				.not( '.checkmatrix-forced' );
			// Otherwise treat it as a normal form input.
			$prefInput.prop( 'disabled', !enabled );
		}
	}

	/**
	 * Enable and disable the related preference field when selecting the local exception checkbox.
	 */
	mw.hook( 'htmlform.enhance' ).add( function ( $root ) {
		$root.find( '.mw-globalprefs-local-exception.oo-ui-checkboxInputWidget' ).each( function () {
			var checkbox = OO.ui.infuse( this );
			// Update on change.
			checkbox.on( 'change', function () {
				updatePrefInput( checkbox.$input.attr( 'name' ), checkbox.isSelected() );
			} );
			// Also update once on initialization, to get it in sync.
			updatePrefInput( checkbox.$input.attr( 'name' ), checkbox.isSelected() );
		} );
	} );

}() );
