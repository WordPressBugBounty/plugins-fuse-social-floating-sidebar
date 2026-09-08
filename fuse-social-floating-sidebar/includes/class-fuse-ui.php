<?php
/**
 * Inline UI icons (Tabler Icons, MIT-licensed) rendered as stroke SVGs.
 * Brand/network icons live in Fuse_Networks; these are the admin + frontend
 * chrome glyphs (grips, chevrons, card headers, etc). Self-contained — no
 * webfont or CDN dependency.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuse_UI {

	private static $paths = array(
		'adjustments-horizontal' => array( 'M14 6m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0', 'M4 6l8 0', 'M16 6l4 0', 'M8 12m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0', 'M4 12l2 0', 'M10 12l10 0', 'M17 18m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0', 'M4 18l11 0', 'M19 18l1 0' ),
		'anchor' => array( 'M12 9v12m-8 -8a8 8 0 0 0 16 0m1 0h-2m-14 0h-2', 'M12 6m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0' ),
		'arrows-sort' => array( 'M3 9l4 -4l4 4m-4 -4v14', 'M21 15l-4 4l-4 -4m4 4v-14' ),
		'box' => array( 'M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5', 'M12 12l8 -4.5', 'M12 12l0 9', 'M12 12l-8 -4.5' ),
		'category' => array( 'M4 4h6v6h-6z', 'M14 4h6v6h-6z', 'M4 14h6v6h-6z', 'M17 17m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0' ),
		'chart-bar' => array( 'M3 12m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z', 'M9 8m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z', 'M15 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z', 'M4 20l14 0' ),
		'chart-line' => array( 'M4 19l16 0', 'M4 15l4 -6l4 2l4 -5l4 4' ),
		'chart-pie' => array( 'M10 3.2a9 9 0 1 0 10.8 10.8a1 1 0 0 0 -1 -1h-6.8a2 2 0 0 1 -2 -2v-7a.9 .9 0 0 0 -1 -.8', 'M15 3.5a9 9 0 0 1 5.5 5.5h-4.5a1 1 0 0 1 -1 -1v-4.5' ),
		'check' => array( 'M5 12l5 5l10 -10' ),
		'chevron-down' => array( 'M6 9l6 6l6 -6' ),
		'chevron-up' => array( 'M6 15l6 -6l6 6' ),
		'code' => array( 'M7 8l-4 4l4 4', 'M17 8l4 4l-4 4', 'M14 4l-4 16' ),
		'device-floppy' => array( 'M6 4h10l4 4v10a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2', 'M12 14m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0', 'M14 4l0 4l-6 0l0 -4' ),
		'device-mobile' => array( 'M6 5a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2v-14z', 'M11 4h2', 'M12 17v.01' ),
		'download' => array( 'M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2', 'M7 11l5 5l5 -5', 'M12 4l0 12' ),
		'external-link' => array( 'M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6', 'M11 13l9 -9', 'M15 4h5v5' ),
		'eye' => array( 'M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0', 'M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6' ),
		'file-text' => array( 'M14 3v4a1 1 0 0 0 1 1h4', 'M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z', 'M9 9l1 0', 'M9 13l6 0', 'M9 17l6 0' ),
		'grip-vertical' => array( 'M9 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0', 'M9 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0', 'M9 19m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0', 'M15 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0', 'M15 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0', 'M15 19m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0' ),
		'hash' => array( 'M5 9l14 0', 'M5 15l14 0', 'M11 4l-4 16', 'M17 4l-4 16' ),
		'help-circle' => array( 'M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0', 'M12 16v.01', 'M12 13a2 2 0 0 0 .914 -3.782a1.98 1.98 0 0 0 -2.414 .483' ),
		'layout-align-top' => array( 'M4 4l16 0', 'M9 8m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z' ),
		'layout-sidebar-right' => array( 'M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z', 'M15 4l0 16' ),
		'link' => array( 'M9 15l6 -6', 'M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464', 'M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463' ),
		'mail' => array( 'M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10z', 'M3 7l9 6l9 -6' ),
		'message-circle' => array( 'M3 20l1.3 -3.9c-2.324 -3.437 -1.426 -7.872 2.1 -10.374c3.526 -2.501 8.59 -2.296 11.845 .48c3.255 2.777 3.695 7.266 1.029 10.501c-2.666 3.235 -7.615 4.215 -11.574 2.293l-4.7 1' ),
		'package' => array( 'M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5', 'M12 12l8 -4.5', 'M12 12l0 9', 'M12 12l-8 -4.5', 'M16 5.25l-8 4.5' ),
		'palette' => array( 'M12 21a9 9 0 0 1 0 -18c4.97 0 9 3.582 9 8c0 1.06 -.474 2.078 -1.318 2.828c-.844 .75 -1.989 1.172 -3.182 1.172h-2.5a2 2 0 0 0 -1 3.75a1.3 1.3 0 0 1 -1 2.25', 'M8.5 10.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0', 'M12.5 7.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0', 'M16.5 10.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0' ),
		'plus' => array( 'M12 5l0 14', 'M5 12l14 0' ),
		'puzzle' => array( 'M4 7h3a1 1 0 0 0 1 -1v-1a2 2 0 0 1 4 0v1a1 1 0 0 0 1 1h3a1 1 0 0 1 1 1v3a1 1 0 0 0 1 1h1a2 2 0 0 1 0 4h-1a1 1 0 0 0 -1 1v3a1 1 0 0 1 -1 1h-3a1 1 0 0 1 -1 -1v-1a2 2 0 0 0 -4 0v1a1 1 0 0 1 -1 1h-3a1 1 0 0 1 -1 -1v-3a1 1 0 0 1 1 -1h1a2 2 0 0 0 0 -4h-1a1 1 0 0 1 -1 -1v-3a1 1 0 0 1 1 -1' ),
		'refresh' => array( 'M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4', 'M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4' ),
		'share-3' => array( 'M13 4v4c-6.575 1.028 -9.02 6.788 -10 12c-.037 .206 5.384 -5.962 10 -6v4l8 -7l-8 -7z' ),
		'shield-lock' => array( 'M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3', 'M12 11m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0', 'M12 12l0 2.5' ),
		'social' => array( 'M12 5m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0', 'M5 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0', 'M19 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0', 'M12 14m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0', 'M12 7l0 4', 'M6.7 17.8l2.8 -2', 'M17.3 17.8l-2.8 -2' ),
		'trash' => array( 'M4 7l16 0', 'M10 11l0 6', 'M14 11l0 6', 'M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12', 'M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3' ),
		'trending-up' => array( 'M3 17l6 -6l4 4l8 -8', 'M14 7l7 0l0 7' ),
		'upload' => array( 'M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2', 'M7 9l5 -5l5 5', 'M12 4l0 12' ),
		'user-off' => array( 'M8.18 8.189a4.01 4.01 0 0 0 2.616 2.627m3.507 -.545a4 4 0 1 0 -5.59 -5.552', 'M6 21v-2a4 4 0 0 1 4 -4h4c.412 0 .81 .062 1.183 .178m2.633 2.618c.12 .38 .184 .785 .184 1.204v2', 'M3 3l18 18' ),
		'users' => array( 'M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0', 'M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2', 'M16 3.13a4 4 0 0 1 0 7.75', 'M21 21v-2a4 4 0 0 0 -3 -3.85' ),
		'world' => array( 'M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0', 'M3.6 9h16.8', 'M3.6 15h16.8', 'M11.5 3a17 17 0 0 0 0 18', 'M12.5 3a17 17 0 0 1 0 18' ),
		'x' => array( 'M18 6l-12 12', 'M6 6l12 12' ),
		'printer' => array( 'M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2', 'M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4', 'M7 13m0 2a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2z' ),
	);

	/**
	 * Render an inline stroke SVG icon by Tabler name.
	 */
	public static function icon( $name, $size = 18, $class = '' ) {
		if ( empty( self::$paths[ $name ] ) ) {
			return '';
		}

		$inner = '';
		foreach ( self::$paths[ $name ] as $d ) {
			$inner .= '<path d="' . esc_attr( $d ) . '"/>';
		}

		return sprintf(
			'<svg class="fsi-ui-icon %4$s" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			absint( $size ),
			$inner,
			'',
			esc_attr( $class )
		);
	}
}
