/* Fuse Social Icons — front-end behavior */
( function () {
	'use strict';

	function initCopyLink() {
		document.querySelectorAll( '.fsi-copy-link' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var url = el.getAttribute( 'data-copy-url' );
				var done = function () {
					el.classList.add( 'fsi-copied' );
					setTimeout( function () { el.classList.remove( 'fsi-copied' ); }, 1600 );
				};
				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( url ).then( done );
				} else {
					var t = document.createElement( 'textarea' );
					t.value = url; t.style.position = 'fixed'; t.style.opacity = '0';
					document.body.appendChild( t ); t.select();
					try { document.execCommand( 'copy' ); } catch ( err ) {}
					document.body.removeChild( t ); done();
				}
			} );
		} );
	}

	function initPrintShare() {
		document.querySelectorAll( '.fsi-print-btn' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				window.print();
			} );
		} );
	}

	function initNativeShare() {
		document.querySelectorAll( '.fsi-native-share' ).forEach( function ( el ) {
			if ( ! navigator.share ) {
				el.remove(); // device share sheet unsupported — hide the button.
				return;
			}
			el.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				navigator.share( {
					url: el.getAttribute( 'data-copy-url' ),
					title: el.getAttribute( 'data-share-title' ) || document.title,
				} ).catch( function () {} );
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initCopyLink();
		initPrintShare();
		initNativeShare();
	} );
} )();
