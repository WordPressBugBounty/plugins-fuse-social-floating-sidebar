/* Fuse Social Icons — admin interactivity */
( function ( $ ) {
	'use strict';

	var app = document.querySelector( '.fsi-app' );
	if ( ! app ) {
		return;
	}

	/* ---------------- Tabs (sliding pill) ---------------- */
	function initTabs() {
		var tabs = app.querySelectorAll( '.fsi-tab' );
		var pill = document.getElementById( 'fsi-pill' );

		function movePill( el ) {
			if ( el && pill ) {
				pill.style.left = el.offsetLeft + 'px';
				pill.style.width = el.offsetWidth + 'px';
			}
		}
		function activate( el ) {
			tabs.forEach( function ( t ) { t.classList.remove( 'on' ); } );
			el.classList.add( 'on' );
			movePill( el );
			var tab = el.getAttribute( 'data-tab' );
			app.setAttribute( 'data-tab', tab );
			var hidden = document.getElementById( 'fsi-active-tab' );
			if ( hidden ) { hidden.value = tab; }
		}
		tabs.forEach( function ( t ) {
			t.addEventListener( 'click', function () { activate( t ); } );
		} );

		var current = app.querySelector( '.fsi-tab.on' ) || tabs[0];
		if ( current ) { movePill( current ); }
		window.addEventListener( 'resize', function () {
			movePill( app.querySelector( '.fsi-tab.on' ) );
		} );
	}

	/* ---------------- Sticky save bar ---------------- */
	var savebar = document.getElementById( 'fsi-savebar' );
	var dirty = false;
	function markDirty() {
		if ( ! dirty ) { dirty = true; savebar && savebar.classList.add( 'show' ); }
	}
	function initSaveBar() {
		document.addEventListener( 'change', function ( e ) {
			if ( e.target.closest( '.fsi-app' ) ) { markDirty(); }
		} );
		document.addEventListener( 'input', function ( e ) {
			if ( e.target.matches( '.fsi-range, .fsi-fld, .fsi-code-input, input[type=url], input[type=text], input[type=email], input[type=number], textarea' ) && e.target.closest( '.fsi-app' ) ) {
				markDirty();
			}
		} );
		var discard = document.getElementById( 'fsi-discard' );
		if ( discard ) {
			discard.addEventListener( 'click', function () {
				dirty = false; window.location.reload();
			} );
		}
	}

	/* ---------------- Color pickers (WP iris; panel floats via CSS) ---------------- */
	function initColors( $ctx ) {
		$ctx.find( '.fsi-color' ).each( function () {
			if ( ! $( this ).closest( '.wp-picker-container' ).length ) {
				$( this ).wpColorPicker( {
					change: function () { markDirty(); setTimeout( updateDock, 20 ); },
					clear: function () { markDirty(); setTimeout( updateDock, 20 ); },
				} );
			}
		} );

		// Close any open picker when clicking elsewhere on the page.
		$( document ).off( 'click.fsiColor' ).on( 'click.fsiColor', function ( e ) {
			if ( ! $( e.target ).closest( '.wp-picker-container' ).length ) {
				$( '.wp-picker-active .wp-color-result' ).trigger( 'click' );
			}
		} );
	}

	/* ---------------- Segmented sync + shape preview ---------------- */
	function initSegmented() {
		app.querySelectorAll( '.fsi-segopt input' ).forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				var group = input.closest( '.fsi-seg' );
				group.querySelectorAll( '.fsi-segopt' ).forEach( function ( o ) { o.classList.remove( 'on' ); } );
				input.closest( '.fsi-segopt' ).classList.add( 'on' );
				updateDock();
			} );
		} );
	}

	/* ---------------- Sliders ---------------- */
	function initSliders() {
		app.querySelectorAll( '.fsi-range' ).forEach( function ( r ) {
			r.addEventListener( 'input', function () {
				var out = r.closest( '.fsi-stack' ).querySelector( '.fsi-val' );
				if ( out ) { out.textContent = r.value + ( out.getAttribute( 'data-unit' ) || '' ); }
				if ( 'fuse[general][size]' === r.name || 'fuse[general][custom_radius]' === r.name || 'fuse[general][gap]' === r.name ) { updateDock(); }
			} );
		} );
	}

	/* ---------------- Pro upsell modal + locked-control click intercept ---------------- */
	function initProLocks() {
		var modal = document.getElementById( 'fsi-pro-modal' );
		if ( ! modal ) { return; }
		var closeBtn = document.getElementById( 'fsi-pro-modal-close' );

		function openProModal() {
			modal.hidden = false;
		}
		function closeProModal() {
			modal.hidden = true;
		}

		document.addEventListener( 'click', function ( e ) {
			var trigger = e.target.closest( '[data-pro-open]' );
			if ( ! trigger ) { return; }
			e.preventDefault(); // suppress the native toggle/select/radio default action underneath.
			openProModal();
		} );

		if ( closeBtn ) { closeBtn.addEventListener( 'click', closeProModal ); }
		modal.addEventListener( 'click', function ( e ) {
			if ( e.target === modal ) { closeProModal(); }
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! modal.hidden ) { closeProModal(); }
		} );
	}

	/* ---------------- Custom-color visibility ---------------- */
	function initColorStyle() {
		var sel = app.querySelector( 'select[name="fuse[general][color_style]"]' );
		if ( ! sel ) { return; }
		sel.addEventListener( 'change', function () {
			var box = app.querySelector( '.fsi-custom-colors' );
			if ( box ) { box.style.display = sel.value === 'custom' ? '' : 'none'; }
		} );
	}

	/* ---------------- Profile: activate / remove networks ---------------- */
	var netCounter = 10000;

	function buildRow( key, color, label, urltype, url ) {
		var idx = netCounter++;
		var svg = ''; // filled by cloning if available
		var placeholder = urltype === 'mailto' ? 'you@example.com' : 'https://' + key + '.com/yourpage';
		var type = urltype === 'mailto' ? 'email' : 'url';
		var row = document.createElement( 'div' );
		row.className = 'fsi-irow';
		row.setAttribute( 'data-key', key );
		row.setAttribute( 'data-color', color );
		row.innerHTML =
			'<span class="fsi-grip">' + gripSvg() + '</span>' +
			'<span class="fsi-chip" style="--chip:' + color + '">' + ( iconCache[ key ] || '' ) + '</span>' +
			'<span class="fsi-nm">' + label + '</span>' +
			'<input type="hidden" form="fsi-form" class="fsi-key" name="fuse[networks][' + idx + '][key]" value="' + key + '">' +
			'<input type="checkbox" form="fsi-form" class="fsi-en" name="fuse[networks][' + idx + '][enabled]" value="1" checked hidden>' +
			'<input type="' + type + '" form="fsi-form" class="fsi-fld" name="fuse[networks][' + idx + '][url]" value="' + ( url || '' ) + '" placeholder="' + placeholder + '">' +
			'<button type="button" class="fsi-rm">' + xSvg() + '</button>';
		return row;
	}

	function buildSwatch( key, color, label, urltype, url ) {
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = 'fsi-add fsi-swatch';
		b.setAttribute( 'data-key', key );
		b.setAttribute( 'data-color', color );
		b.setAttribute( 'data-label', label );
		b.setAttribute( 'data-urltype', urltype || 'url' );
		b.setAttribute( 'data-url', url || '' );
		b.setAttribute( 'title', label );
		b.style.setProperty( '--chip', color );
		b.innerHTML = iconCache[ key ] || '';
		return b;
	}

	var iconCache = {};
	function cacheIcons() {
		// Cache each network's SVG markup from whatever is already rendered.
		app.querySelectorAll( '.fsi-irow[data-key], .fsi-swatch[data-key]' ).forEach( function ( el ) {
			var key = el.getAttribute( 'data-key' );
			if ( iconCache[ key ] ) { return; }
			var svg = el.querySelector( '.fsi-chip svg, svg' );
			if ( svg ) { iconCache[ key ] = svg.outerHTML; }
		} );
	}
	function gripSvg() { var g = app.querySelector( '.fsi-grip svg' ); return g ? g.outerHTML : ''; }
	function xSvg() { var g = app.querySelector( '.fsi-rm svg' ); return g ? g.outerHTML : '&times;'; }

	function initProfileNetworks() {
		var active = document.getElementById( 'fsi-active-list' );
		var addList = document.getElementById( 'fsi-add-list' );
		if ( ! active || ! addList ) { return; }

		addList.addEventListener( 'click', function ( e ) {
			var sw = e.target.closest( '.fsi-swatch' );
			if ( ! sw ) { return; }
			if ( sw.hasAttribute( 'data-pro-open' ) ) { return; } // locked network: the document-level Pro modal handler takes over.
			var row = buildRow( sw.getAttribute( 'data-key' ), sw.getAttribute( 'data-color' ), sw.getAttribute( 'data-label' ), sw.getAttribute( 'data-urltype' ), sw.getAttribute( 'data-url' ) );
			active.appendChild( row );
			sw.remove();
			$( active ).sortable( 'refresh' );
			row.querySelector( '.fsi-fld' ).focus();
			markDirty(); updateDock();
		} );

		active.addEventListener( 'click', function ( e ) {
			var rm = e.target.closest( '.fsi-rm' );
			if ( ! rm ) { return; }
			var row = rm.closest( '.fsi-irow' );
			var custom = document.getElementById( 'fsi-add-custom' );
			var sw = buildSwatch( row.getAttribute( 'data-key' ), row.getAttribute( 'data-color' ), row.querySelector( '.fsi-nm' ).textContent, row.querySelector( '.fsi-fld' ).getAttribute( 'type' ) === 'email' ? 'mailto' : 'url', row.querySelector( '.fsi-fld' ).value );
			addList.insertBefore( sw, custom );
			row.remove();
			markDirty(); updateDock();
		} );

		// Sort A–Z
		var sortBtn = document.getElementById( 'fsi-sort-az' );
		if ( sortBtn ) {
			sortBtn.addEventListener( 'click', function () {
				var rows = Array.prototype.slice.call( active.querySelectorAll( '.fsi-irow' ) );
				rows.sort( function ( a, b ) {
					return a.querySelector( '.fsi-nm' ).textContent.localeCompare( b.querySelector( '.fsi-nm' ).textContent );
				} );
				rows.forEach( function ( r ) { active.appendChild( r ); } );
				markDirty(); updateDock();
			} );
		}
	}

	/* ---------------- Share: activate / remove ---------------- */
	function initShareNetworks() {
		var list = document.getElementById( 'fsi-share-list' );
		var addList = document.getElementById( 'fsi-share-add' );
		if ( ! list || ! addList ) { return; }

		addList.addEventListener( 'click', function ( e ) {
			var sw = e.target.closest( '.fsi-share-swatch' );
			if ( ! sw ) { return; }
			var key = sw.getAttribute( 'data-key' ), color = sw.getAttribute( 'data-color' ), label = sw.getAttribute( 'data-label' );
			var row = document.createElement( 'div' );
			row.className = 'fsi-irow';
			row.setAttribute( 'data-key', key );
			row.setAttribute( 'data-color', color );
			row.innerHTML =
				'<span class="fsi-grip">' + gripSvg() + '</span>' +
				'<span class="fsi-chip" style="--chip:' + color + '">' + ( sw.innerHTML ) + '</span>' +
				'<span class="fsi-nm" style="width:120px">' + label + '</span>' +
				'<input type="hidden" form="fsi-form" class="fsi-share-key" name="fuse[share][networks][]" value="' + key + '">' +
				'<span class="fsi-metric"></span>' +
				'<button type="button" class="fsi-rm">' + xSvg() + '</button>';
			list.appendChild( row );
			sw.remove();
			$( list ).sortable( 'refresh' );
			markDirty();
		} );

		list.addEventListener( 'click', function ( e ) {
			var rm = e.target.closest( '.fsi-rm' );
			if ( ! rm ) { return; }
			var row = rm.closest( '.fsi-irow' );
			var sw = document.createElement( 'button' );
			sw.type = 'button';
			sw.className = 'fsi-add fsi-share-swatch';
			sw.setAttribute( 'data-key', row.getAttribute( 'data-key' ) );
			sw.setAttribute( 'data-color', row.getAttribute( 'data-color' ) );
			sw.setAttribute( 'data-label', row.querySelector( '.fsi-nm' ).textContent );
			sw.setAttribute( 'title', row.querySelector( '.fsi-nm' ).textContent );
			sw.style.setProperty( '--chip', row.getAttribute( 'data-color' ) );
			sw.innerHTML = row.querySelector( '.fsi-chip' ).innerHTML;
			addList.appendChild( sw );
			row.remove();
			markDirty();
		} );
	}

	/* ---------------- Live preview dock ---------------- */
	// Mirrors EVERY appearance option: shape, custom radius, size, color style
	// (brand / mono / custom colors), hover effect, and drop shadow. Hover any
	// preview icon to see the selected hover animation.
	function field( sel ) { return app.querySelector( sel ); }

	function updateDock() {
		var dock = document.getElementById( 'fsi-dock' );
		if ( ! dock ) { return; }
		var active = document.getElementById( 'fsi-active-list' );

		var shapeInput = field( 'input[name="fuse[general][shape]"]:checked' );
		var shape      = shapeInput ? shapeInput.value : 'round';
		var size       = parseInt( ( field( 'input[name="fuse[general][size]"]' ) || {} ).value, 10 ) || 34;
		var gap        = parseInt( ( field( 'input[name="fuse[general][gap]"]' ) || {} ).value, 10 );
		var customRad  = parseInt( ( field( 'input[name="fuse[general][custom_radius]"]' ) || {} ).value, 10 ) || 0;
		var colorStyle = ( field( 'select[name="fuse[general][color_style]"]' ) || {} ).value || 'brand';
		var customBg   = ( field( 'input[name="fuse[general][custom_bg_color]"]' ) || {} ).value || '#131118';
		var customFg   = ( field( 'input[name="fuse[general][custom_icon_color]"]' ) || {} ).value || '#ffffff';
		var hover      = ( field( 'select[name="fuse[general][hover_effect]"]' ) || {} ).value || 'lift';
		var shadowCb   = field( 'input[name="fuse[general][shadow]"]' );
		var shadow     = shadowCb ? shadowCb.checked : true;

		var radius = customRad > 0 ? customRad + 'px' : ( { round: '50%', rounded: '25%', square: '0', outline: '50%' }[ shape ] || '50%' );

		dock.className = 'fsi-dock fsi-dock-hover-' + hover + ( shadow ? ' fsi-dock-shadow' : '' );
		dock.style.gap = ( isNaN( gap ) ? 12 : gap ) + 'px';
		dock.innerHTML = '';

		var rows = active ? active.querySelectorAll( '.fsi-irow' ) : [];
		if ( ! rows.length ) {
			dock.innerHTML = '<span class="fsi-dock-empty">Add icons to preview</span>';
			return;
		}

		rows.forEach( function ( row ) {
			var brand = row.getAttribute( 'data-color' ) || '#666';
			var bg = brand, fg = '#fff', edge = '';

			if ( 'mono-dark' === colorStyle )       { bg = '#131118'; fg = '#ffffff'; }
			else if ( 'mono-light' === colorStyle ) { bg = '#ffffff'; fg = '#131118'; edge = '1px solid rgba(0,0,0,.14)'; }
			else if ( 'custom' === colorStyle )     { bg = customBg || '#131118'; fg = customFg || '#ffffff'; }

			// Outline: transparent fill, colored border + icon — not a filled swatch.
			if ( 'outline' === shape && customRad <= 0 ) {
				fg = bg;
				edge = '2px solid ' + bg;
				bg = 'transparent';
			}

			var di = document.createElement( 'span' );
			di.className = 'fsi-di';
			di.style.width = size + 'px';
			di.style.height = size + 'px';
			di.style.background = bg;
			di.style.borderRadius = radius;
			if ( edge ) { di.style.border = edge; }

			var svg = row.querySelector( '.fsi-chip svg' );
			if ( svg ) {
				var clone = svg.cloneNode( true );
				clone.style.width = Math.round( size * 0.5 ) + 'px';
				clone.style.height = Math.round( size * 0.5 ) + 'px';
				clone.style.color = fg;
				di.appendChild( clone );
			}
			dock.appendChild( di );
		} );
	}

	function initPreviewBindings() {
		$( document ).on(
			'change',
			'input[name="fuse[general][shape]"], select[name="fuse[general][hover_effect]"], select[name="fuse[general][color_style]"], input[name="fuse[general][shadow]"]',
			updateDock
		);
	}

	/* ---------------- Sortables ---------------- */
	function initSortables() {
		[ '#fsi-active-list', '#fsi-share-list' ].forEach( function ( sel ) {
			var el = document.querySelector( sel );
			if ( el ) {
				$( el ).sortable( { handle: '.fsi-grip', axis: 'y', update: function () { markDirty(); updateDock(); } } );
			}
		} );
	}

	/* ---------------- Reindex on submit ---------------- */
	function initSubmit() {
		var form = document.getElementById( 'fsi-form' );
		if ( ! form ) { return; }
		form.addEventListener( 'submit', function () {
			var active = document.getElementById( 'fsi-active-list' );
			if ( active ) {
				active.querySelectorAll( '.fsi-irow' ).forEach( function ( row, idx ) {
					row.querySelectorAll( '[name^="fuse[networks]"]' ).forEach( function ( inp ) {
						inp.name = inp.name.replace( /fuse\[networks\]\[[^\]]+\]/, 'fuse[networks][' + idx + ']' );
					} );
				} );
			}
			dirty = false;
		} );
	}

	$( function () {
		cacheIcons();
		initTabs();
		initSaveBar();
		initColors( $( document ) );
		initProLocks();
		initSegmented();
		initSliders();
		initColorStyle();
		initSortables();
		initProfileNetworks();
		initShareNetworks();
		initSubmit();
		initPreviewBindings();
		updateDock();
	} );
} )( jQuery );
