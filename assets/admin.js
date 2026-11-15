/* CondoClock admin: wizard coverage text, board search, drop zone and dialogs. No outside requests. */
( function () {
	'use strict';

	// Wizard step 1: units or parcels label, timeshare question and the coverage sentence.
	var wiz = document.querySelector( '.ccw' );
	if ( wiz ) {
		var cover = wiz.querySelector( '.ccw-cover' );
		var update = function () {
			var mode = wiz.querySelector( 'input[name=mode]:checked' );
			var count = wiz.querySelector( 'input[name=count]' );
			var share = wiz.querySelector( 'input[name=timeshare]' );
			mode = mode ? mode.value : 'condo';
			wiz.setAttribute( 'data-mode', mode );
			if ( ! cover || ! count ) {
				return;
			}
			var n = parseInt( count.value, 10 ) || 0;
			var yes = 'hoa' === mode ? n >= 100 : n >= 25 && ! ( share && share.checked );
			cover.textContent = cover.getAttribute( yes ? 'data-yes' : 'data-no' );
			cover.classList.toggle( 'no', ! yes );
		};
		wiz.addEventListener( 'input', update );
		wiz.addEventListener( 'change', update );
		update();
	}

	// Board search.
	var search = document.getElementById( 'ccb-q' );
	if ( search ) {
		search.addEventListener( 'input', function () {
			var q = search.value.toLowerCase().trim();
			document.querySelectorAll( '.ccb-row[data-t]' ).forEach( function ( row ) {
				row.hidden = q && row.getAttribute( 'data-t' ).indexOf( q ) === -1;
			} );
			document.querySelectorAll( '.ccb-groupwrap' ).forEach( function ( g ) {
				g.hidden = ! g.querySelector( '.ccb-row:not([hidden])' );
			} );
		} );
	}

	// Drop zone: highlight and pass dropped files to the file input.
	document.querySelectorAll( '.ccb-drop' ).forEach( function ( zone ) {
		var input = zone.querySelector( 'input[type=file]' );
		var list = zone.querySelector( '.ccb-picked' );
		var show = function () {
			if ( list && input ) {
				list.textContent = Array.prototype.map.call( input.files, function ( f ) {
					return f.name;
				} ).join( ', ' );
			}
		};
		[ 'dragenter', 'dragover' ].forEach( function ( ev ) {
			zone.addEventListener( ev, function ( e ) {
				e.preventDefault();
				zone.classList.add( 'over' );
			} );
		} );
		[ 'dragleave', 'drop' ].forEach( function ( ev ) {
			zone.addEventListener( ev, function () {
				zone.classList.remove( 'over' );
			} );
		} );
		zone.addEventListener( 'drop', function ( e ) {
			e.preventDefault();
			if ( input && e.dataTransfer && e.dataTransfer.files.length ) {
				input.files = e.dataTransfer.files;
				show();
			}
		} );
		if ( input ) {
			input.addEventListener( 'change', show );
		}
	} );

	// Dialogs: buttons with data-open, and dialogs that open on load.
	document.querySelectorAll( '[data-open]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var d = document.getElementById( btn.getAttribute( 'data-open' ) );
			if ( d && d.showModal ) {
				d.showModal();
			}
		} );
	} );
	document.querySelectorAll( '[data-close]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var d = btn.closest( 'dialog' );
			if ( d ) {
				d.close();
			}
		} );
	} );
	document.querySelectorAll( 'dialog[data-autoopen]' ).forEach( function ( d ) {
		if ( d.showModal ) {
			d.showModal();
		}
	} );
}() );
