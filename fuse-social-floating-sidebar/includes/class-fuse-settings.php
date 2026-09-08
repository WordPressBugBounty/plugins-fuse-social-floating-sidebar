<?php
/**
 * Settings schema, defaults, and sanitization.
 *
 * Storage model: this plugin shares the SAME wp_options row (`fuse`) that both
 * the legacy free and premium "Fuse Social Floating Sidebar" plugins use, so an
 * upgrade needs no migration step at all:
 *
 *  - READ:  if `fuse` contains our namespaced `_fsi` subkey, that clean
 *           structure is used. Otherwise (a site coming straight from the old
 *           plugin) the legacy Redux keys are normalized in-memory on the fly.
 *  - WRITE: the clean structure is saved under `fuse['_fsi']`, all untouched
 *           legacy keys are preserved, and the overlapping legacy keys are
 *           synced back (Fuse_Migrator::denormalize_into) so the old plugin
 *           still renders correctly if it is ever reactivated.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuse_Settings {

	const OPTION_KEY = 'fuse';
	const SUBKEY     = '_fsi';

	private static $cache = null;

	public static function defaults() {
		return array(
			'_version'  => FUSE_SOCIAL_ICONS_VERSION,
			'_migrated' => false,
			'general'   => array(
				// Matches the legacy free plugin's fresh-install look exactly:
				// square, 48px, no shadow, no hover animation (see Design 1 /
				// `size` default '48' / `shadow` default '1' / unset animation_on_hover
				// in the legacy Redux config).
				'shape'             => 'square',
				'custom_radius'     => 0, // px; > 0 overrides the shape preset (legacy border_radius_icon). Pro only.
				'size'              => 48,
				'gap'               => 8, // px between icons (legacy fixed 8px gap).
				'color_style'       => 'brand',
				'custom_bg_color'   => '#111214',
				'custom_icon_color' => '#ffffff',
				'hover_effect'      => 'none',
				'shadow'            => false,
				'open_new_tab'      => true,
				'nofollow'          => false,
				'custom_icons_first' => false, // legacy custom_icon_on_top. Pro only.
			),
			'networks'  => array(
				array( 'key' => 'facebook', 'url' => '', 'enabled' => false ),
				array( 'key' => 'instagram', 'url' => '', 'enabled' => false ),
				array( 'key' => 'youtube', 'url' => '', 'enabled' => false ),
				array( 'key' => 'x', 'url' => '', 'enabled' => false ),
				array( 'key' => 'linkedin', 'url' => '', 'enabled' => false ),
			),
			'custom_icons' => array(),
			'floating'  => array(
				'enabled'             => true,
				'position'            => 'left',
				'vertical_align'      => 'middle',
				'vertical_offset_pct' => 25, // used when vertical_align = custom (legacy verticalpos).
				'hide_on_scroll_down' => false,
				'show_on_mobile'      => true,
				'sticky_mobile_bar'   => false, // full-width bottom bar on mobile (legacy stickybar).
				'appear_after_scroll' => false,
				'scroll_offset'       => 300,
				'reveal_on_click'     => false,
				'reveal_mobile_only'  => false, // group behind the button on mobile only (legacy action_on_mob).
				'chat_bubble'         => array(
					'enabled' => false,
					'network' => 'whatsapp',
					'value'   => '',
					'message' => 'Hi! I have a question.',
				),
			),
			'share'     => array(
				'enabled'        => false,
				'networks'       => array( 'facebook', 'x', 'whatsapp', 'link' ),
				'before'         => true, // "after" is Pro-only in the free build.
				'after'          => false,
				'floating_sidebar' => false,
				'sticky_mobile'  => false,
				'style'          => 'icon',
				'shape'          => 'square', // free tier only offers square.
				'size'           => 34,
				'icon_size'      => 17, // px; independent of button size so the glyph doesn't get lost as the button grows.
			),
			'display_rules' => array(),
			'advanced'  => array(
				'custom_css'   => '',
			),
			'visibility' => array(
				'excluded_page_ids' => array(),
				'hide_on_posts'     => false,
			),
			'action_button' => array(
				'icon'                => 'plus', // legacy intial_font_icon (FontAwesome class) preserved here.
				'bg_color'            => '#5a0fcb',
				'border_color'        => '#5a0fcb',
				'active_border_color' => '#48079e',
				'icon_shadow'         => false, // legacy active_social_shadow.
				'icon_hover_animate'  => false, // legacy active_animate_hover.
				'spacing'             => array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ), // legacy active_social_spacings.
			),
		);
	}

	public static function get() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$raw = get_option( self::OPTION_KEY, array() );

		if ( is_array( $raw ) && isset( $raw[ self::SUBKEY ] ) && is_array( $raw[ self::SUBKEY ] ) ) {
			// Our clean structure already lives inside the shared option.
			self::$cache = self::enforce_free_tier( self::merge_defaults( $raw[ self::SUBKEY ] ) );
			return self::$cache;
		}

		// Pre-1.0 dev builds stored the clean structure under their own option
		// key; adopt it once (it gets re-saved under fuse[_fsi] on next save).
		$interim = get_option( 'fuse_social_settings', array() );
		if ( is_array( $interim ) && ! empty( $interim['networks'] ) ) {
			self::$cache = self::enforce_free_tier( self::merge_defaults( $interim ) );
			return self::$cache;
		}

		// Fresh from the legacy plugin (or empty): normalize on the fly.
		$normalized  = Fuse_Migrator::normalize(
			is_array( $raw ) ? $raw : array(),
			get_option( 'fuse_social_options', array() )
		);
		self::$cache = self::enforce_free_tier( self::merge_defaults( $normalized ) );

		return self::$cache;
	}

	/**
	 * @param array $settings    Clean settings to persist under fuse[_fsi].
	 * @param bool  $sync_legacy Also mirror overlapping legacy keys (default).
	 *                           Pass false when the legacy data must stay
	 *                           untouched (e.g. wizard "start fresh").
	 */
	public static function update( $settings, $sync_legacy = true ) {
		self::$cache = null;

		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$raw[ self::SUBKEY ] = $settings;

		if ( $sync_legacy ) {
			// Keep the overlapping legacy keys in sync so the old free/premium
			// plugin still renders correctly if it is ever reactivated.
			$raw = Fuse_Migrator::denormalize_into( $raw, $settings );
		}

		return update_option( self::OPTION_KEY, $raw );
	}

	/**
	 * Deep-merge saved settings over defaults so newly-added keys always exist.
	 */
	public static function merge_defaults( $saved ) {
		$defaults = self::defaults();
		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return $defaults;
		}

		$merged = $defaults;

		foreach ( array( 'general', 'floating', 'share', 'advanced', 'visibility', 'action_button' ) as $group ) {
			if ( ! empty( $saved[ $group ] ) && is_array( $saved[ $group ] ) ) {
				$merged[ $group ] = self::deep_merge( $defaults[ $group ], $saved[ $group ] );
			}
		}

		foreach ( array( 'networks', 'custom_icons', 'display_rules' ) as $list ) {
			if ( isset( $saved[ $list ] ) && is_array( $saved[ $list ] ) ) {
				$merged[ $list ] = $saved[ $list ];
			}
		}

		foreach ( array( '_version', '_migrated', '_migrated_at', '_migration_report' ) as $meta ) {
			if ( isset( $saved[ $meta ] ) ) {
				$merged[ $meta ] = $saved[ $meta ];
			}
		}

		// Coerce sizes: earlier builds stored an sm/md/lg enum; the schema now
		// uses pixels, so a stale enum would otherwise read as 0 (invisible).
		$merged['general']['size'] = self::coerce_size( $merged['general']['size'], $defaults['general']['size'] );
		$merged['share']['size']   = self::coerce_size( $merged['share']['size'], $defaults['share']['size'] );

		return $merged;
	}

	private static function coerce_size( $val, $default ) {
		if ( is_numeric( $val ) ) {
			$n = (int) $val;
			return ( $n >= 16 && $n <= 80 ) ? $n : $default;
		}
		$map = array( 'sm' => 26, 'md' => 34, 'lg' => 44 );
		return $map[ (string) $val ] ?? $default;
	}

	private static function deep_merge( $defaults, $saved ) {
		$out = $defaults;
		foreach ( $saved as $k => $v ) {
			if ( isset( $defaults[ $k ] ) && is_array( $defaults[ $k ] ) && is_array( $v ) ) {
				$out[ $k ] = self::deep_merge( $defaults[ $k ], $v );
			} else {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	/**
	 * Free-build gating: clamps every Pro-only field to its free-safe value.
	 * A no-op when Fuse_Pro::is_pro() is true (the Pro build never calls this
	 * with anything to clamp against). Applied on BOTH save (sanitize()) and
	 * every read (get()) so it also covers stale data left over from a
	 * downgrade or a direct DB edit — not just the admin form path.
	 */
	private static function enforce_free_tier( $settings ) {
		if ( Fuse_Pro::is_pro() ) {
			return $settings;
		}

		$defaults = self::defaults();

		// ---- Profile icons. ----
		if ( ! in_array( $settings['general']['shape'], array( 'round', 'square' ), true ) ) {
			$settings['general']['shape'] = $defaults['general']['shape'];
		}
		$settings['general']['custom_radius'] = 0;
		if ( ! in_array( $settings['general']['color_style'], array( 'brand', 'custom' ), true ) ) {
			$settings['general']['color_style'] = $defaults['general']['color_style'];
		}
		if ( ! in_array( $settings['general']['hover_effect'], array( 'none', 'rotate' ), true ) ) {
			$settings['general']['hover_effect'] = $defaults['general']['hover_effect'];
		}
		$settings['general']['custom_icons_first'] = false;

		// Per-network color overrides are Pro only.
		foreach ( $settings['networks'] as &$row ) {
			unset( $row['bg_color'], $row['icon_color'], $row['hover_bg_color'], $row['hover_icon_color'] );
		}
		unset( $row );

		// Networks outside the legacy free plugin's original set (WhatsApp,
		// Telegram, TikTok, etc.) are Pro only — drop those rows so a crafted
		// save (or stale data from a licence downgrade) can't sneak one in.
		$settings['networks'] = array_values( array_filter( $settings['networks'], function ( $row ) {
			return ! Fuse_Networks::is_pro_network( $row['key'] );
		} ) );

		// Custom icons are entirely Pro.
		$settings['custom_icons'] = array();

		// ---- Floating bar. ----
		if ( ! in_array( $settings['floating']['position'], array( 'left', 'right' ), true ) ) {
			$settings['floating']['position'] = $defaults['floating']['position'];
		}
		$settings['floating']['vertical_align']      = 'middle';
		$settings['floating']['hide_on_scroll_down'] = false;
		$settings['floating']['sticky_mobile_bar']   = false;
		$settings['floating']['appear_after_scroll'] = false;
		$settings['floating']['reveal_on_click']     = false;
		$settings['floating']['reveal_mobile_only']  = false;
		$settings['floating']['chat_bubble']['enabled'] = false;

		// ---- Share buttons. ----
		$settings['share']['after']            = false;
		$settings['share']['floating_sidebar']  = false;
		$settings['share']['sticky_mobile']     = false;
		$settings['share']['style']             = 'icon';
		$settings['share']['shape']             = 'square';

		// ---- Display rules & page/post visibility — entirely Pro. ----
		$settings['display_rules']                = array();
		$settings['visibility']['excluded_page_ids'] = array();
		$settings['visibility']['hide_on_posts']     = false;

		return $settings;
	}

	/* ------------------------------------------------------------------ */

	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$existing = self::get();
		$clean    = $existing;

		if ( isset( $input['general'] ) ) {
			$g = $input['general'];
			$clean['general'] = array(
				'shape'             => self::pick( $g, 'shape', array( 'round', 'rounded', 'square', 'outline' ), $defaults['general']['shape'] ),
				'custom_radius'     => self::clamp_int( $g['custom_radius'] ?? null, 0, 100, $defaults['general']['custom_radius'] ),
				'size'              => self::clamp_int( $g['size'] ?? null, 16, 80, $defaults['general']['size'] ),
				'gap'               => self::clamp_int( $g['gap'] ?? null, 0, 60, $defaults['general']['gap'] ),
				'color_style'       => self::pick( $g, 'color_style', array( 'brand', 'mono-dark', 'mono-light', 'custom' ), $defaults['general']['color_style'] ),
				'custom_bg_color'   => self::hex( $g['custom_bg_color'] ?? '', $defaults['general']['custom_bg_color'] ),
				'custom_icon_color' => self::hex( $g['custom_icon_color'] ?? '', $defaults['general']['custom_icon_color'] ),
				'hover_effect'      => self::pick( $g, 'hover_effect', array( 'none', 'lift', 'pulse', 'shake', 'rotate', 'flip', 'bounce', 'swing' ), $defaults['general']['hover_effect'] ),
				'shadow'            => ! empty( $g['shadow'] ),
				'open_new_tab'      => ! empty( $g['open_new_tab'] ),
				'nofollow'          => ! empty( $g['nofollow'] ),
				'custom_icons_first' => ! empty( $g['custom_icons_first'] ),
			);
		}

		if ( isset( $input['networks'] ) && is_array( $input['networks'] ) ) {
			$networks = array();
			foreach ( $input['networks'] as $row ) {
				if ( empty( $row['key'] ) ) {
					continue;
				}
				$net = array(
					'key'     => sanitize_key( $row['key'] ),
					'url'     => self::network_url( $row['key'], $row['url'] ?? '' ),
					'enabled' => ! empty( $row['enabled'] ),
				);
				foreach ( array( 'bg_color', 'icon_color', 'hover_bg_color', 'hover_icon_color' ) as $c ) {
					if ( ! empty( $row[ $c ] ) ) {
						$net[ $c ] = self::hex( $row[ $c ], '' );
					}
				}
				$networks[] = $net;
			}
			$clean['networks'] = $networks;
		}

		if ( isset( $input['custom_icons'] ) && is_array( $input['custom_icons'] ) ) {
			$icons = array();
			foreach ( $input['custom_icons'] as $row ) {
				if ( empty( $row['url'] ) && empty( $row['image_url'] ) && empty( $row['title'] ) ) {
					continue;
				}
				$icons[] = array(
					'title'            => sanitize_text_field( $row['title'] ?? '' ),
					'image_id'         => absint( $row['image_id'] ?? 0 ),
					'image_url'        => esc_url_raw( $row['image_url'] ?? '' ),
					'font_icon'        => sanitize_text_field( $row['font_icon'] ?? '' ), // legacy FontAwesome class, preserved.
					'icon_prefix'      => in_array( $row['icon_prefix'] ?? '', array( 'fas', 'far', 'fab' ), true ) ? $row['icon_prefix'] : '',
					'icon_name'        => sanitize_key( $row['icon_name'] ?? '' ),
					'icon_size'        => self::clamp_int( $row['icon_size'] ?? null, 16, 200, 40 ),
					'url'              => esc_url_raw( $row['url'] ?? '' ),
					'bg_color'         => self::hex( $row['bg_color'] ?? '', '' ),
					'icon_color'       => self::hex( $row['icon_color'] ?? '', '' ),
					'hover_bg_color'   => self::hex( $row['hover_bg_color'] ?? '', '' ),
					'hover_icon_color' => self::hex( $row['hover_icon_color'] ?? '', '' ),
				);
			}
			$clean['custom_icons'] = $icons;
		}

		if ( isset( $input['floating'] ) ) {
			$f  = $input['floating'];
			$cb = $f['chat_bubble'] ?? array();
			$clean['floating'] = array(
				'enabled'             => ! empty( $f['enabled'] ),
				'position'            => self::pick( $f, 'position', array( 'left', 'right', 'bottom' ), $defaults['floating']['position'] ),
				'vertical_align'      => self::pick( $f, 'vertical_align', array( 'top', 'middle', 'bottom', 'custom' ), $defaults['floating']['vertical_align'] ),
				'vertical_offset_pct' => self::clamp_int( $f['vertical_offset_pct'] ?? null, 1, 100, $defaults['floating']['vertical_offset_pct'] ),
				'hide_on_scroll_down' => ! empty( $f['hide_on_scroll_down'] ),
				'show_on_mobile'      => ! empty( $f['show_on_mobile'] ),
				'sticky_mobile_bar'   => ! empty( $f['sticky_mobile_bar'] ),
				'appear_after_scroll' => ! empty( $f['appear_after_scroll'] ),
				'scroll_offset'       => self::clamp_int( $f['scroll_offset'] ?? null, 0, 5000, $defaults['floating']['scroll_offset'] ),
				'reveal_on_click'     => ! empty( $f['reveal_on_click'] ),
				'reveal_mobile_only'  => ! empty( $f['reveal_mobile_only'] ),
				'chat_bubble'         => array(
					// Pro feature — only persists as enabled when Pro is active.
					'enabled' => ! empty( $cb['enabled'] ) && Fuse_Pro::is_pro(),
					'network' => self::pick( $cb, 'network', array( 'whatsapp', 'messenger', 'telegram' ), 'whatsapp' ),
					'value'   => sanitize_text_field( $cb['value'] ?? '' ),
					'message' => sanitize_text_field( $cb['message'] ?? '' ),
				),
			);
		}

		if ( isset( $input['action_button'] ) ) {
			$a  = $input['action_button'];
			$sp = $a['spacing'] ?? array();
			$clean['action_button'] = array(
				'icon'                => sanitize_text_field( $a['icon'] ?? $defaults['action_button']['icon'] ),
				'bg_color'            => self::hex( $a['bg_color'] ?? '', $defaults['action_button']['bg_color'] ),
				'border_color'        => self::hex( $a['border_color'] ?? '', $defaults['action_button']['border_color'] ),
				'active_border_color' => self::hex( $a['active_border_color'] ?? '', $defaults['action_button']['active_border_color'] ),
				'icon_shadow'         => ! empty( $a['icon_shadow'] ),
				'icon_hover_animate'  => ! empty( $a['icon_hover_animate'] ),
				'spacing'             => array(
					'top'    => self::clamp_int( $sp['top'] ?? null, 0, 100, 0 ),
					'right'  => self::clamp_int( $sp['right'] ?? null, 0, 100, 0 ),
					'bottom' => self::clamp_int( $sp['bottom'] ?? null, 0, 100, 0 ),
					'left'   => self::clamp_int( $sp['left'] ?? null, 0, 100, 0 ),
				),
			);
		}

		if ( isset( $input['share'] ) ) {
			$s        = $input['share'];
			$valid    = array_keys( Fuse_Networks::share_networks() );
			$networks = array();
			if ( ! empty( $s['networks'] ) && is_array( $s['networks'] ) ) {
				foreach ( $s['networks'] as $n ) {
					if ( in_array( $n, $valid, true ) ) {
						$networks[] = $n;
					}
				}
			}
			$clean['share'] = array(
				'enabled'          => ! empty( $s['enabled'] ),
				'networks'         => $networks,
				'before'           => ! empty( $s['before'] ),
				'after'            => ! empty( $s['after'] ),
				'floating_sidebar' => ! empty( $s['floating_sidebar'] ),
				'sticky_mobile'    => ! empty( $s['sticky_mobile'] ),
				'style'            => self::pick( $s, 'style', array( 'icon', 'icon-label', 'button' ), $defaults['share']['style'] ),
				'shape'            => self::pick( $s, 'shape', array( 'round', 'rounded', 'square', 'outline' ), $defaults['share']['shape'] ),
				'size'             => self::clamp_int( $s['size'] ?? null, 16, 80, $defaults['share']['size'] ),
				'icon_size'        => self::clamp_int( $s['icon_size'] ?? null, 10, 48, $defaults['share']['icon_size'] ),
			);
		}

		// Display rules (page/post targeting) are a Pro-only feature — always
		// stored empty in this build regardless of what's posted.
		$clean['display_rules'] = array();

		if ( isset( $input['visibility'] ) ) {
			$v   = $input['visibility'];
			$ids = array();
			if ( ! empty( $v['excluded_page_ids'] ) ) {
				$raw = is_array( $v['excluded_page_ids'] ) ? $v['excluded_page_ids'] : explode( ',', (string) $v['excluded_page_ids'] );
				$ids = array_values( array_filter( array_map( 'absint', $raw ) ) );
			}
			$clean['visibility'] = array(
				'excluded_page_ids' => $ids,
				'hide_on_posts'     => ! empty( $v['hide_on_posts'] ),
			);
		}

		if ( isset( $input['advanced'] ) ) {
			$a = $input['advanced'];
			$clean['advanced'] = array(
				'custom_css'   => self::sanitize_css( $a['custom_css'] ?? '' ),
			);
		}

		return self::enforce_free_tier( $clean );
	}

	private static function sanitize_css( $css ) {
		$css = (string) wp_unslash( $css );
		// Strip tags to prevent </style> breakout; keep CSS syntax intact.
		$css = wp_strip_all_tags( $css );
		return trim( $css );
	}

	private static function network_url( $key, $url ) {
		$network  = Fuse_Networks::get_profile_network( $key );
		$url_type = $network['url_type'] ?? 'url';

		if ( 'mailto' === $url_type ) {
			$url = trim( (string) $url );
			if ( '' === $url ) {
				return '';
			}
			if ( 0 !== strpos( $url, 'mailto:' ) ) {
				$url = 'mailto:' . $url;
			}
			$email = str_replace( 'mailto:', '', $url );
			return is_email( $email ) ? 'mailto:' . sanitize_email( $email ) : '';
		}

		return esc_url_raw( $url );
	}

	/* ---- small helpers ---- */

	private static function pick( $arr, $key, $allowed, $default ) {
		return isset( $arr[ $key ] ) && in_array( $arr[ $key ], $allowed, true ) ? $arr[ $key ] : $default;
	}

	private static function clamp_int( $val, $min, $max, $default ) {
		if ( null === $val || '' === $val || ! is_numeric( $val ) ) {
			return $default;
		}
		return max( $min, min( $max, (int) $val ) );
	}

	private static function hex( $val, $default ) {
		$c = sanitize_hex_color( $val );
		return $c ? $c : $default;
	}
}
