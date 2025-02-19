( function () {
	'use strict';

	/**
	 * Alter the search index to add local exception fields (T332851).
	 *
	 * @param {string} index Search index
	 */
	mw.hook( 'prefs.search.buildIndex' ).add( ( index ) => {
		Object.keys( index ).forEach( ( key ) => {
			index[ key ].forEach( ( item ) => {
				const $localException = item.$field.next( '.mw-globalprefs-local-exception' );
				if ( $localException.length ) {
					item.$field = item.$field.add( $localException );
				}
			} );
		} );
	} );
}() );
