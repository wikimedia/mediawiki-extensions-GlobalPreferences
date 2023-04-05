( function () {
	'use strict';

	/**
	 * Alter the search index to add local exception fields (T332851).
	 *
	 * @param {string} index Search index
	 */
	mw.hook( 'prefs.search.buildIndex' ).add( function ( index ) {
		Object.keys( index ).forEach( function ( key ) {
			index[ key ].forEach( function ( item ) {
				var $localException = item.$field.next( '.mw-globalprefs-local-exception' );
				if ( $localException.length ) {
					item.$field = item.$field.add( $localException );
				}
			} );
		} );
	} );
}() );
