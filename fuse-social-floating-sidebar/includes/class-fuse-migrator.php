<?php
/**
 * Bidirectional normalizer between the legacy "Fuse Social Floating Sidebar"
 * (free + premium) Redux option structure and this plugin's clean schema.
 *
 * Both plugins share the SAME `fuse` option row:
 *   - normalize():         legacy keys  -> clean settings (read path; also the
 *                          automatic "migration", which therefore never needs a
 *                          user-facing step).
 *   - denormalize_into():  clean settings -> legacy keys (write path; keeps the
 *                          old plugin rendering correctly if reactivated).
 *
 * Covers every stored option of the premium plugin v5.5.5, including the
 * premium-only fields: verticalpos, border_radius_icon, stickybar,
 * action_on_mob, select_font_icon, intial_font_icon, active_social_spacings,
 * active_social_shadow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuse_Migrator {

	/** PascalCase opt-sortable keys (Redux schema) -> new lowercase network keys. */
	private static $redux_key_map = array(
		'Facebook'      => 'facebook',
		'Threads'       => 'threads',
		'Twitter'       => 'x',
		'RSS'           => 'rss',
		'Linkedin'      => 'linkedin',
		'Youtube'       => 'youtube',
		'Flickr'        => 'flickr',
		'Stumbleupon'   => 'stumbleupon',
		'Instagram'     => 'instagram',
		'Tumblr'        => 'tumblr',
		'Vine'          => 'vine',
		'VK'            => 'vk',
		'SoundCloud'    => 'soundcloud',
		'Pinterest'     => 'pinterest',
		'Reddit'        => 'reddit',
		'StackOverFlow' => 'stack-overflow',
		'Behance'       => 'behance',
		'Github'        => 'github',
		'Email'         => 'envelope',
	);

	/** Legacy flat (pre-Redux) option keys -> new lowercase network keys. */
	private static $legacy_flat_key_map = array(
		'facebook'    => 'facebook',
		'twitter'     => 'x',
		'rss'         => 'rss',
		'linkedin'    => 'linkedin',
		'youtube'     => 'youtube',
		'flickr'      => 'flickr',
		'pinterest'   => 'pinterest',
		'stumbleupon' => 'stumbleupon',
		'google-plus' => 'google-plus',
		'instagram'   => 'instagram',
		'tumblr'      => 'tumblr',
		'vine'        => 'vine',
		'vk'          => 'vk',
		'soundcloud'  => 'soundcloud',
		'reddit'      => 'reddit',
		'stack'       => 'stack-overflow',
		'behance'     => 'behance',
		'github'      => 'github',
		'envelope'    => 'envelope',
	);

	private static function reverse_key_map() {
		return array_flip( self::$redux_key_map );
	}

	/**
	 * Best-effort map from common legacy FontAwesome action-button icon
	 * classes (the old `intial_font_icon` Redux icon_select field) to the
	 * closest available Fuse_UI glyph. Anything unmapped falls back to
	 * 'plus' by the caller.
	 */
	private static $legacy_action_icon_map = array(
		'fa-comments'       => 'message-circle',
		'fa-comment'        => 'message-circle',
		'fa-comment-o'      => 'message-circle',
		'fa-comments-o'     => 'message-circle',
		'fa-envelope'       => 'mail',
		'fa-envelope-o'     => 'mail',
		'fa-share'          => 'share-3',
		'fa-share-alt'      => 'share-3',
		'fa-share-square'   => 'share-3',
		'fa-share-square-o' => 'share-3',
		'fa-share-alt-square' => 'share-3',
		'fa-plus'           => 'plus',
		'fa-plus-circle'    => 'plus',
		'fa-plus-square'    => 'plus',
		'fa-question'       => 'help-circle',
		'fa-question-circle' => 'help-circle',
		'fa-globe'          => 'world',
		'fa-users'          => 'users',
		'fa-user'           => 'users',
		'fa-user-o'         => 'users',
	);

	/**
	 * Translate a legacy FontAwesome class string (e.g. "fa fa-comments")
	 * into the closest Fuse_UI icon key, defaulting to 'plus'.
	 */
	private static function map_legacy_action_icon( $fa_class ) {
		$fa_class = strtolower( trim( (string) $fa_class ) );
		if ( '' === $fa_class ) {
			return 'plus';
		}
		foreach ( explode( ' ', $fa_class ) as $token ) {
			if ( isset( self::$legacy_action_icon_map[ $token ] ) ) {
				return self::$legacy_action_icon_map[ $token ];
			}
		}
		return 'plus';
	}

	/* ================================================================== *
	 * READ PATH — legacy -> clean
	 * ================================================================== */

	/**
	 * Build a clean settings array from whatever legacy data exists. Pure,
	 * in-memory: never writes to the database.
	 *
	 * @param array $redux Legacy `fuse` option value (Redux structure).
	 * @param array $flat  Legacy `fuse_social_options` value (pre-Redux flat).
	 */
	public static function normalize( $redux, $flat = array() ) {
		$settings = Fuse_Settings::defaults();
		$report   = self::empty_report();

		$has_redux = is_array( $redux ) && self::has_truthy_sortable( $redux );
		$has_flat  = is_array( $flat ) && ! empty( array_filter( (array) $flat ) );

		if ( $has_redux ) {
			$report['source'] = 'fuse (redux)';
			self::normalize_redux( $redux, $settings, $report );
		} elseif ( $has_flat ) {
			$report['source'] = 'fuse_social_options (legacy flat)';
			self::normalize_flat( $flat, $settings, $report );
		} else {
			return $settings; // nothing legacy — plain defaults.
		}

		$settings['_migrated']         = true;
		$settings['_migrated_at']      = current_time( 'mysql' );
		$settings['_migration_report'] = $report;

		return $settings;
	}

	/**
	 * Re-import from the legacy keys and persist (Tools tab button; also seeds
	 * historical click counts into the analytics table).
	 */
	public static function run() {
		$redux = get_option( Fuse_Settings::OPTION_KEY, array() );
		$flat  = get_option( 'fuse_social_options', array() );

		if ( is_array( $redux ) ) {
			unset( $redux[ Fuse_Settings::SUBKEY ] ); // re-import from legacy keys only.
		}

		$has_any = ( is_array( $redux ) && self::has_truthy_sortable( $redux ) )
			|| ( is_array( $flat ) && ! empty( array_filter( (array) $flat ) ) )
			|| get_option( 'fuse_click_data' );

		if ( ! $has_any ) {
			return false;
		}

		$settings = self::normalize( is_array( $redux ) ? $redux : array(), is_array( $flat ) ? $flat : array() );

		Fuse_Settings::update( $settings );

		return $settings['_migration_report'] ?? true;
	}

	/**
	 * Whether any legacy plugin data exists at all (drives the migration wizard).
	 */
	public static function has_legacy_data() {
		$redux = get_option( Fuse_Settings::OPTION_KEY, array() );
		if ( is_array( $redux ) && self::has_truthy_sortable( $redux ) ) {
			return true;
		}
		$flat = get_option( 'fuse_social_options', array() );
		if ( is_array( $flat ) && ! empty( array_filter( $flat ) ) ) {
			return true;
		}
		return (bool) get_option( 'fuse_click_data' );
	}

	private static function empty_report() {
		return array(
			'source'          => 'none',
			'links_imported'  => 0,
			'legacy_networks' => array(),
			'custom_icons'    => 0,
			'clicks_imported' => 0,
			'notes'           => array(),
		);
	}

	private static function has_truthy_sortable( $redux ) {
		if ( empty( $redux['opt-sortable'] ) || ! is_array( $redux['opt-sortable'] ) ) {
			return false;
		}
		foreach ( $redux['opt-sortable'] as $value ) {
			if ( ! empty( $value ) ) {
				return true;
			}
		}
		return false;
	}

	private static function normalize_redux( $fuse, &$settings, &$report ) {
		// ---- Networks (opt-sortable) + per-network colors (color_main). ----
		$networks     = array();
		$color_by_key = self::index_color_main( $fuse['color_main'] ?? null );
		$defunct      = array_keys( Fuse_Networks::legacy_defunct_networks() );

		if ( ! empty( $fuse['opt-sortable'] ) && is_array( $fuse['opt-sortable'] ) ) {
			foreach ( $fuse['opt-sortable'] as $pascal => $url ) {
				if ( empty( $url ) ) {
					continue;
				}
				$key       = self::$redux_key_map[ $pascal ] ?? sanitize_key( $pascal );
				$is_legacy = in_array( $key, $defunct, true );

				$entry = array(
					'key'     => $key,
					'url'     => esc_url_raw( $url ),
					'enabled' => ! $is_legacy,
				);
				if ( isset( $color_by_key[ $pascal ] ) ) {
					$entry = array_merge( $entry, $color_by_key[ $pascal ] );
				}

				$networks[] = $entry;
				$report['links_imported']++;
				if ( $is_legacy ) {
					$report['legacy_networks'][] = $key;
				}
			}
		}
		if ( ! empty( $networks ) ) {
			$settings['networks'] = $networks;
		}

		// ---- General appearance. ----
		$settings['general']['open_new_tab'] = ! empty( $fuse['linksnewtab'] );
		$settings['general']['nofollow']     = ! empty( $fuse['relattr'] );
		$settings['general']['hover_effect'] = ! empty( $fuse['animation_on_hover'] ) ? 'rotate' : 'lift';
		// Legacy "shadow" is INVERTED: truthy meant "don't use shadow".
		$settings['general']['shadow']       = empty( $fuse['shadow'] );
		// Premium: whether custom icons render before the regular networks.
		$settings['general']['custom_icons_first'] = ! empty( $fuse['custom_icon_on_top'] );

		if ( ! empty( $fuse['custom_gen_background_color'] ) ) {
			$settings['general']['custom_bg_color'] = sanitize_hex_color( $fuse['custom_gen_background_color'] );
			$settings['general']['color_style']     = 'custom';
		} elseif ( ! empty( $fuse['change_color'] ) && sanitize_hex_color( $fuse['change_color'] ) ) {
			// Premium plugin v5.5.5 only: the "Use custom background color" field
			// was registered with the SAME id ('change_color') as an unrelated
			// on/off switch ("Change icon color on hover"), a Redux config bug.
			// The color picker registration wins in practice, so the real stored
			// value in $options['change_color'] is a hex color (not the switch's
			// boolean) and IS read by fuse_social_sidebar_scripts.php to set
			// `.awesome-social { background: ... }` — a genuine custom-background
			// feature the free plugin exposes under custom_gen_background_color
			// instead. That key doesn't exist in the premium plugin, so without
			// this branch premium customers using it would silently lose it.
			$settings['general']['custom_bg_color'] = sanitize_hex_color( $fuse['change_color'] );
			$settings['general']['color_style']     = 'custom';
		}

		$size_map = array( '48' => 48, '32' => 32, '24' => 24 );
		$settings['general']['size'] = $size_map[ (string) ( $fuse['size'] ?? '48' ) ] ?? 34;

		$design_map = array( '1' => 'square', '2' => 'round', '3' => 'outline' );
		$settings['general']['shape'] = $design_map[ (string) ( $fuse['design-section'] ?? '' ) ] ?? 'round';

		// Premium: custom border radius (px).
		if ( ! empty( $fuse['border_radius_icon'] ) ) {
			$settings['general']['custom_radius'] = min( 100, absint( $fuse['border_radius_icon'] ) );
		}

		// ---- Floating bar. ----
		$settings['floating']['position']            = ( 'right' === ( $fuse['position'] ?? '' ) ) ? 'right' : 'left';
		$settings['floating']['show_on_mobile']      = empty( $fuse['mobile'] );
		$settings['floating']['appear_after_scroll'] = ! empty( $fuse['scrollpost'] );
		$settings['floating']['scroll_offset']       = isset( $fuse['scrollpos'] ) ? absint( $fuse['scrollpos'] ) : 300;
		$settings['floating']['reveal_on_click']     = ! empty( $fuse['action_button'] );
		// Premium: vertical position % / sticky mobile bar / mobile-only action button.
		if ( isset( $fuse['verticalpos'] ) && '' !== $fuse['verticalpos'] && 25 !== (int) $fuse['verticalpos'] ) {
			$settings['floating']['vertical_align']      = 'custom';
			$settings['floating']['vertical_offset_pct'] = max( 1, min( 100, absint( $fuse['verticalpos'] ) ) );
		}
		$settings['floating']['sticky_mobile_bar']  = ! empty( $fuse['stickybar'] );
		$settings['floating']['reveal_mobile_only'] = ! empty( $fuse['action_on_mob'] );

		// ---- Visibility. ----
		if ( ! empty( $fuse['not_display_on'] ) ) {
			$ids = is_array( $fuse['not_display_on'] ) ? $fuse['not_display_on'] : array( $fuse['not_display_on'] );
			$settings['visibility']['excluded_page_ids'] = array_map( 'absint', $ids );
		}
		$settings['visibility']['hide_on_posts'] = ! empty( $fuse['hide_blog_posts'] );

		// ---- Action-button styling (premium fields included). ----
		$ab =& $settings['action_button'];
		if ( ! empty( $fuse['intial_font_icon'] ) ) {
			$ab['icon'] = self::map_legacy_action_icon( $fuse['intial_font_icon'] );
		} elseif ( ! empty( $fuse['select_icon'] ) ) {
			$ab['icon'] = self::map_legacy_action_icon( $fuse['select_icon'] );
		}
		if ( ! empty( $fuse['button_background_color'] ) ) {
			$grad = $fuse['button_background_color'];
			if ( is_array( $grad ) ) {
				// Redux color_gradient field: ['from'] / ['to'] hex keys —
				// the new schema only supports a single solid color, so take
				// 'from' (falling back to 'to') as the closest honest match.
				$from = $grad['from'] ?? '';
				$to   = $grad['to'] ?? '';
				$bg   = ! empty( $from ) ? $from : $to;
				if ( ! empty( $from ) && ! empty( $to ) && $from !== $to ) {
					$report['notes'][] = __( 'Your action button used a two-color gradient — imported as a single color.', 'fuse-social-floating-sidebar' );
				}
			} else {
				// Plain string value (older/flat data) — use as-is.
				$bg = $grad;
			}
			$ab['bg_color'] = sanitize_hex_color( $bg ) ?: $ab['bg_color'];
		}
		if ( ! empty( $fuse['button_border_color'] ) ) {
			$ab['border_color'] = sanitize_hex_color( $fuse['button_border_color'] ) ?: $ab['border_color'];
		}
		if ( ! empty( $fuse['active_social_border_color'] ) ) {
			$ab['active_border_color'] = sanitize_hex_color( $fuse['active_social_border_color'] ) ?: $ab['active_border_color'];
		}
		$ab['icon_shadow']        = ! empty( $fuse['active_social_shadow'] );
		$ab['icon_hover_animate'] = ! empty( $fuse['active_animate_hover'] );
		if ( ! empty( $fuse['active_social_spacings'] ) && is_array( $fuse['active_social_spacings'] ) ) {
			$sp = $fuse['active_social_spacings'];
			$ab['spacing'] = array(
				'top'    => absint( $sp['padding-top'] ?? 0 ),
				'right'  => absint( $sp['padding-right'] ?? 0 ),
				'bottom' => absint( $sp['padding-bottom'] ?? 0 ),
				'left'   => absint( $sp['padding-left'] ?? 0 ),
			);
		}

		// Custom icons are a Pro-only feature in this build, so legacy custom
		// icon data isn't imported — just noted, so nothing looks silently lost.
		if ( ! empty( $fuse['fuse-custom-icons']['social_icon_url'] ) ) {
			$report['notes'][] = 'Custom icons from your previous version require Fuse Social Icons Pro and were not imported.';
		}

		if ( ! empty( $report['legacy_networks'] ) ) {
			$report['notes'][] = sprintf(
				'%d defunct network link(s) imported but disabled: %s',
				count( $report['legacy_networks'] ),
				implode( ', ', array_unique( $report['legacy_networks'] ) )
			);
		}
	}

	private static function index_color_main( $color_main ) {
		$index = array();
		if ( empty( $color_main ) || ! is_array( $color_main ) || empty( $color_main['social_select'] ) ) {
			return $index;
		}
		$count = count( (array) $color_main['social_select'] );
		for ( $i = 0; $i < $count; $i++ ) {
			$label = $color_main['social_select'][ $i ] ?? '';
			if ( empty( $label ) ) {
				continue;
			}
			$index[ $label ] = array_filter( array(
				'bg_color'         => sanitize_hex_color( $color_main['bg_color'][ $i ] ?? '' ),
				'icon_color'       => sanitize_hex_color( $color_main['icon_m_color'][ $i ] ?? '' ),
				'hover_bg_color'   => sanitize_hex_color( $color_main['hover_bg_color'][ $i ] ?? '' ),
				'hover_icon_color' => sanitize_hex_color( $color_main['hover_icon_m_color'][ $i ] ?? '' ),
			) );
		}
		return $index;
	}

	private static function normalize_flat( $flat, &$settings, &$report ) {
		$networks = array();
		$defunct  = array_keys( Fuse_Networks::legacy_defunct_networks() );

		foreach ( self::$legacy_flat_key_map as $old => $new ) {
			if ( empty( $flat[ $old ] ) ) {
				continue;
			}
			$is_legacy  = in_array( $new, $defunct, true );
			$networks[] = array( 'key' => $new, 'url' => esc_url_raw( $flat[ $old ] ), 'enabled' => ! $is_legacy );
			$report['links_imported']++;
			if ( $is_legacy ) {
				$report['legacy_networks'][] = $new;
			}
		}
		if ( ! empty( $networks ) ) {
			$settings['networks'] = $networks;
		}

		$settings['general']['open_new_tab'] = ( '1' == ( $flat['linksnewtab'] ?? '' ) );
		$settings['general']['nofollow']     = ( '1' == ( $flat['relattr'] ?? '' ) );
		$settings['general']['hover_effect'] = ! empty( $flat['animation_on_hover'] ) ? 'rotate' : 'lift';
		$settings['general']['shadow']       = empty( $flat['shadow'] );

		if ( ! empty( $flat['custom_gen_background_color'] ) ) {
			$settings['general']['custom_bg_color'] = sanitize_hex_color( $flat['custom_gen_background_color'] );
			$settings['general']['color_style']     = 'custom';
		}

		$size_map = array( '48' => 48, '32' => 32, '24' => 24 );
		$settings['general']['size'] = $size_map[ (string) ( $flat['size'] ?? '48' ) ] ?? 34;

		$design_map = array( '1' => 'square', '2' => 'round', '3' => 'outline' );
		$settings['general']['shape'] = $design_map[ (string) ( $flat['design-section'] ?? '' ) ] ?? 'round';

		$settings['floating']['show_on_mobile'] = empty( $flat['mobile'] );
	}

	/* ================================================================== *
	 * WRITE PATH — clean -> legacy (keeps the old plugin coherent)
	 * ================================================================== */

	/**
	 * Sync the overlapping legacy keys inside the raw `fuse` option array from
	 * the clean settings. Unmapped legacy keys are left untouched.
	 *
	 * @param array $raw      Full raw `fuse` option value (will be returned modified).
	 * @param array $settings Clean settings array.
	 */
	public static function denormalize_into( $raw, $settings ) {
		$g  = $settings['general'];
		$f  = $settings['floating'];
		$v  = $settings['visibility'];
		$ab = $settings['action_button'];

		// ---- opt-sortable: rebuild in saved order (enabled => url, disabled => ''). ----
		$reverse  = self::reverse_key_map();
		$sortable = array();
		foreach ( $settings['networks'] as $row ) {
			$pascal = $reverse[ $row['key'] ] ?? null;
			if ( null === $pascal ) {
				continue; // new-only networks (whatsapp, tiktok…) have no legacy slot.
			}
			$sortable[ $pascal ] = ! empty( $row['enabled'] ) ? $row['url'] : '';
		}
		// Keep any legacy entries we didn't touch (defensive).
		if ( ! empty( $raw['opt-sortable'] ) && is_array( $raw['opt-sortable'] ) ) {
			foreach ( $raw['opt-sortable'] as $pascal => $url ) {
				if ( ! array_key_exists( $pascal, $sortable ) ) {
					$sortable[ $pascal ] = $url;
				}
			}
		}
		$raw['opt-sortable'] = $sortable;

		// ---- Per-network color overrides -> color_main parallel arrays. ----
		$color_main = array( 'social_select' => array(), 'bg_color' => array(), 'icon_m_color' => array(), 'hover_bg_color' => array(), 'hover_icon_m_color' => array() );
		foreach ( $settings['networks'] as $row ) {
			$pascal = $reverse[ $row['key'] ] ?? null;
			if ( null === $pascal ) {
				continue;
			}
			if ( empty( $row['bg_color'] ) && empty( $row['icon_color'] ) && empty( $row['hover_bg_color'] ) && empty( $row['hover_icon_color'] ) ) {
				continue;
			}
			$color_main['social_select'][]      = $pascal;
			$color_main['bg_color'][]           = $row['bg_color'] ?? '';
			$color_main['icon_m_color'][]       = $row['icon_color'] ?? '';
			$color_main['hover_bg_color'][]     = $row['hover_bg_color'] ?? '';
			$color_main['hover_icon_m_color'][] = $row['hover_icon_color'] ?? '';
		}
		if ( ! empty( $color_main['social_select'] ) ) {
			$raw['color_main'] = $color_main;
		}

		// ---- General. ----
		$raw['linksnewtab']        = $g['open_new_tab'] ? '1' : '';
		$raw['relattr']            = $g['nofollow'] ? '1' : '';
		$raw['animation_on_hover'] = ( 'rotate' === $g['hover_effect'] ) ? '1' : '';
		$raw['animate_sec']        = $raw['animate_sec'] ?? '0.5';
		$raw['shadow']             = $g['shadow'] ? '' : '1'; // legacy semantics inverted.
		$raw['border_radius_icon'] = (string) absint( $g['custom_radius'] );
		$raw['custom_icon_on_top'] = ! empty( $g['custom_icons_first'] ) ? '1' : '';

		// Nearest legacy size bucket.
		$px  = absint( $g['size'] );
		$raw['size'] = (string) ( $px >= 41 ? 48 : ( $px >= 29 ? 32 : 24 ) );

		$raw['design-section'] = ( 'square' === $g['shape'] ) ? '1' : ( ( 'outline' === $g['shape'] ) ? '3' : '2' );

		if ( 'custom' === $g['color_style'] && ! empty( $g['custom_bg_color'] ) ) {
			$raw['custom_gen_background_color'] = $g['custom_bg_color'];
			// Premium plugin reads this same custom background color from its
			// own 'change_color' key (see normalize_redux() for why) — keep it
			// in sync too so reactivating the premium plugin still renders it.
			$raw['change_color'] = $g['custom_bg_color'];
		} else {
			$raw['custom_gen_background_color'] = '';
			$raw['change_color']                = '';
		}

		// ---- Floating. ----
		$raw['position']    = ( 'right' === $f['position'] ) ? 'right' : 'left'; // legacy has no bottom.
		$raw['mobile']      = $f['show_on_mobile'] ? '' : '1'; // legacy truthy = hidden on mobile.
		$raw['scrollpost']  = $f['appear_after_scroll'] ? '1' : '0';
		$raw['scrollpos']   = (string) absint( $f['scroll_offset'] );
		$raw['verticalpos'] = (string) ( 'custom' === $f['vertical_align'] ? absint( $f['vertical_offset_pct'] ) : 25 );
		$raw['stickybar']     = $f['sticky_mobile_bar'] ? '1' : '';
		$raw['action_on_mob'] = $f['reveal_mobile_only'] ? '1' : '';
		$raw['action_button'] = $f['reveal_on_click'] ? '1' : '';

		// ---- Visibility. ----
		$raw['not_display_on']  = array_map( 'absint', $v['excluded_page_ids'] );
		$raw['hide_blog_posts'] = $v['hide_on_posts'] ? '1' : '';

		// ---- Action button styling. ----
		$raw['intial_font_icon']           = $ab['icon'];
		$raw['button_background_color']    = $ab['bg_color'];
		$raw['button_border_color']        = $ab['border_color'];
		$raw['active_social_border_color'] = $ab['active_border_color'];
		$raw['active_social_shadow']       = $ab['icon_shadow'] ? '1' : '';
		$raw['active_animate_hover']       = ! empty( $ab['icon_hover_animate'] ) ? '1' : '';
		$raw['active_social_spacings']     = array(
			'padding-top'    => (string) absint( $ab['spacing']['top'] ),
			'padding-right'  => (string) absint( $ab['spacing']['right'] ),
			'padding-bottom' => (string) absint( $ab['spacing']['bottom'] ),
			'padding-left'   => (string) absint( $ab['spacing']['left'] ),
		);

		// ---- Custom icons -> parallel-array repeater. ----
		$rep = array(
			'title_field' => array(), 'select_font_icon' => array(), 'icon_url' => array(),
			'icon-size' => array(), 'social_icon_url' => array(), 'bg_color' => array(),
			'icon_m_color' => array(), 'icon__hv_m_color' => array(), 'icon__hbg_m_color' => array(),
		);
		foreach ( $settings['custom_icons'] as $icon ) {
			$rep['title_field'][]       = $icon['title'] ?? '';
			$rep['select_font_icon'][]  = $icon['font_icon'] ?? '';
			$rep['icon_url'][]          = array( 'url' => $icon['image_url'] ?? '', 'title' => $icon['title'] ?? '' );
			$rep['icon-size'][]         = (string) absint( $icon['icon_size'] ?? 40 );
			$rep['social_icon_url'][]   = $icon['url'] ?? '';
			$rep['bg_color'][]          = $icon['bg_color'] ?? '';
			$rep['icon_m_color'][]      = $icon['icon_color'] ?? '';
			$rep['icon__hv_m_color'][]  = $icon['hover_icon_color'] ?? '';
			$rep['icon__hbg_m_color'][] = $icon['hover_bg_color'] ?? '';
		}
		$raw['fuse-custom-icons'] = ! empty( $rep['social_icon_url'] ) ? $rep : '';

		return $raw;
	}
}
