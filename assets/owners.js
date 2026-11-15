( function () {
	var box = document.querySelector( '.op-search input' );
	if ( ! box ) {
		return;
	}
	box.addEventListener( 'input', function () {
		var q = box.value.trim().toLowerCase();
		document.querySelectorAll( '.op-sec' ).forEach( function ( sec ) {
			var shown = 0;
			sec.querySelectorAll( '.op-file' ).forEach( function ( row ) {
				var hit = ! q || row.getAttribute( 'data-t' ).indexOf( q ) > -1;
				row.hidden = ! hit;
				shown += hit ? 1 : 0;
			} );
			sec.hidden = q && ! shown;
		} );
		var none = document.querySelector( '.op-none' );
		if ( none ) {
			none.hidden = ! q || !! document.querySelector( '.op-sec:not([hidden]) .op-file:not([hidden])' );
		}
	} );
} )();
