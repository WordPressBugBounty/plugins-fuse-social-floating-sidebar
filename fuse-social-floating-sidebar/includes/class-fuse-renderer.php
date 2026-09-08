<?php
/**
 * Shared, context-agnostic HTML renderer used by all surfaces: the floating
 * bar (wp_footer), share buttons (content filter / shortcode), the widget, and
 * the block. One renderer keeps markup identical everywhere.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuse_Renderer {

	/**
	 * Render the profile-links icon group.
	 *
	 * @param array $settings Full settings array.
	 * @param array $args     'mode' (floating|inline), 'wrap_id', 'wrap_class',
	 *                        plus optional per-instance overrides 'shape','size'.
	 */
	public static function render_profile_icons( $settings, $args = array() ) {
		$general = $settings['general'];
		$items   = self::get_enabled_profile_items( $settings );

		if ( empty( $items ) ) {
			return '';
		}

		$mode     = $args['mode'] ?? 'inline';
		$floating = $settings['floating'];
		$shape    = ! empty( $args['shape'] ) ? $args['shape'] : $general['shape'];
		$size     = ! empty( $args['size'] ) ? absint( $args['size'] ) : absint( $general['size'] );

		$target   = ! empty( $general['open_new_tab'] ) ? ' target="_blank"' : '';
		$rel_bits = array( 'noopener' );
		if ( ! empty( $general['nofollow'] ) ) {
			$rel_bits[] = 'nofollow';
		}
		$rel = ' rel="' . esc_attr( implode( ' ', $rel_bits ) ) . '"';

		$use_custom_radius = empty( $args['shape'] ) && absint( $general['custom_radius'] ) > 0;

		$classes = array(
			'fsi-wrap',
			$use_custom_radius ? 'fsi-shape-custom' : 'fsi-shape-' . sanitize_html_class( $shape ),
			'fsi-color-' . sanitize_html_class( $general['color_style'] ),
			'fsi-hover-' . sanitize_html_class( $general['hover_effect'] ),
			! empty( $general['shadow'] ) ? 'fsi-shadow' : '',
		);

		if ( 'floating' === $mode ) {
			$classes[] = 'fsi-floating';
			$classes[] = 'fsi-pos-' . sanitize_html_class( $floating['position'] );
			$classes[] = 'fsi-valign-' . sanitize_html_class( $floating['vertical_align'] );
			if ( empty( $floating['show_on_mobile'] ) ) {
				$classes[] = 'fsi-hide-mobile';
			}
		}

		$style_bits = array( '--fsi-size:' . $size . 'px', '--fsi-gap:' . absint( $general['gap'] ) . 'px' );
		if ( $use_custom_radius ) {
			$style_bits[] = '--fsi-radius:' . absint( $general['custom_radius'] ) . 'px';
		}
		self::color_style_root( $general, $style_bits );

		ob_start();
		?>
		<div<?php echo ! empty( $args['wrap_id'] ) ? ' id="' . esc_attr( $args['wrap_id'] ) . '"' : ''; ?> class="<?php echo esc_attr( implode( ' ', array_filter( $classes ) ) . ( ! empty( $args['wrap_class'] ) ? ' ' . $args['wrap_class'] : '' ) ); ?>" style="<?php echo esc_attr( implode( ';', $style_bits ) ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<a<?php echo $target . $rel; ?> href="<?php echo esc_url( $item['url'] ); ?>" class="fsi-icon<?php echo ! empty( $item['is_custom'] ) ? ' fsi-custom' : ''; ?>" data-network="<?php echo esc_attr( $item['key'] ); ?>" data-context="profile" title="<?php echo esc_attr( $item['label'] ); ?>" aria-label="<?php echo esc_attr( $item['label'] ); ?>" style="<?php echo esc_attr( self::item_vars( $item, $general ) ); ?>">
					<?php if ( ! empty( $item['image_url'] ) ) : ?>
						<img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="<?php echo esc_attr( $item['label'] ); ?>" style="width:<?php echo absint( $item['icon_size'] ); ?>px;height:<?php echo absint( $item['icon_size'] ); ?>px;" />
					<?php else : ?>
						<?php echo Fuse_Networks::get_icon_svg( $item['icon_key'], round( $size * 0.5 ) ); // phpcs:ignore ?>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render social share buttons for a URL/title.
	 */
	public static function render_share_buttons( $settings, $share_url, $share_title, $args = array() ) {
		$s = $settings['share'];

		if ( empty( $s['enabled'] ) || empty( $s['networks'] ) ) {
			return '';
		}

		$size      = ! empty( $args['size'] ) ? absint( $args['size'] ) : absint( $s['size'] );
		$icon_size = ! empty( $args['icon_size'] ) ? absint( $args['icon_size'] ) : absint( $s['icon_size'] ?? round( $size * 0.5 ) );
		$shape   = ! empty( $args['shape'] ) ? $args['shape'] : $s['shape'];

		$style_class = array(
			'icon'       => 'fsi-style-icon',
			'icon-label' => 'fsi-style-label',
			'button'     => 'fsi-style-button',
		)[ $s['style'] ] ?? 'fsi-style-icon';

		$classes = array(
			'fsi-share',
			'fsi-shape-' . sanitize_html_class( $shape ),
			$style_class,
		);
		if ( ! empty( $args['wrap_class'] ) ) {
			$classes[] = $args['wrap_class'];
		}

		$networks   = Fuse_Networks::share_networks();
		$has_label  = in_array( $s['style'], array( 'icon-label', 'button' ), true );
		// Channels resolved client-side rather than via a share URL.
		$client_side = array(
			'link'   => 'fsi-copy-link',
			'print'  => 'fsi-print-btn',
			'native' => 'fsi-native-share',
		);
		// Non-brand glyphs come from the UI icon set.
		$ui_glyphs = array( 'print' => 'printer', 'native' => 'share-3' );

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="--fsi-size:<?php echo esc_attr( $size ); ?>px;">
			<?php foreach ( $s['networks'] as $key ) :
				if ( empty( $networks[ $key ] ) ) {
					continue;
				}
				$net       = $networks[ $key ];
				$href      = Fuse_Share_Links::build( $key, $share_url, $share_title );
				$is_client = isset( $client_side[ $key ] );
				$glyph     = isset( $ui_glyphs[ $key ] )
					? Fuse_UI::icon( $ui_glyphs[ $key ], $icon_size )
					: Fuse_Networks::get_icon_svg( $key, $icon_size );
				?>
				<a href="<?php echo $is_client ? '#' : esc_url( $href ); ?>" class="fsi-icon fsi-share-icon<?php echo $is_client ? ' ' . esc_attr( $client_side[ $key ] ) : ''; ?>" data-network="<?php echo esc_attr( $key ); ?>" data-context="share" data-copy-url="<?php echo esc_url( $share_url ); ?>" data-share-title="<?php echo esc_attr( $share_title ); ?>"<?php echo $is_client ? '' : ' target="_blank" rel="noopener"'; ?> title="<?php echo esc_attr( $net['label'] ); ?>" aria-label="<?php echo esc_attr( $net['label'] ); ?>" style="--fsi-bg:<?php echo esc_attr( $net['color'] ); ?>;--fsi-fg:#fff;--fsi-icon-size:<?php echo esc_attr( $icon_size ); ?>px;">
					<span class="fsi-share-ic"><?php echo $glyph; // phpcs:ignore ?></span>
					<?php if ( $has_label ) : ?>
						<span class="fsi-share-label"><?php echo esc_html( $net['label'] ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function get_enabled_profile_items( $settings ) {
		$networks = array();

		foreach ( $settings['networks'] as $row ) {
			if ( empty( $row['enabled'] ) || empty( $row['url'] ) ) {
				continue;
			}
			if ( Fuse_Networks::is_pro_network( $row['key'] ) ) {
				continue;
			}
			$network = Fuse_Networks::get_profile_network( $row['key'] );
			if ( ! $network ) {
				continue;
			}
			$networks[] = array_merge( $row, array(
				'label'    => $network['label'],
				'icon_key' => $row['key'],
				'color'    => $network['color'],
			) );
		}

		// Custom icons are a Pro-only feature in this build, so there's
		// nothing else to merge in here.
		return $networks;
	}

	/**
	 * Root-level CSS vars driven by the color style.
	 */
	private static function color_style_root( $general, &$style_bits ) {
		switch ( $general['color_style'] ) {
			case 'mono-dark':
				$style_bits[] = '--fsi-force-bg:#111214';
				$style_bits[] = '--fsi-force-fg:#ffffff';
				break;
			case 'mono-light':
				$style_bits[] = '--fsi-force-bg:#ffffff';
				$style_bits[] = '--fsi-force-fg:#111214';
				break;
			case 'custom':
				if ( ! empty( $general['custom_bg_color'] ) ) {
					$style_bits[] = '--fsi-force-bg:' . $general['custom_bg_color'];
				}
				if ( ! empty( $general['custom_icon_color'] ) ) {
					$style_bits[] = '--fsi-force-fg:' . $general['custom_icon_color'];
				}
				break;
		}
	}

	private static function item_vars( $item, $general ) {
		// Per-network color overrides are a Pro-only feature in this build.
		return sprintf( '--fsi-bg:%s;--fsi-fg:#fff;', esc_attr( $item['color'] ) );
	}

	/**
	 * The Pro chat bubble (WhatsApp / Messenger / Telegram).
	 */
	public static function render_chat_bubble( $settings ) {
		$cb = $settings['floating']['chat_bubble'];
		if ( empty( $cb['enabled'] ) || empty( $cb['value'] ) || ! Fuse_Pro::is_pro() ) {
			return '';
		}

		$href  = Fuse_Share_Links::chat_bubble_url( $cb['network'], $cb['value'], $cb['message'] );
		$color = array( 'whatsapp' => '#25D366', 'messenger' => '#0084FF', 'telegram' => '#26A5E4' )[ $cb['network'] ] ?? '#25D366';
		$icon  = array( 'whatsapp' => 'whatsapp', 'messenger' => 'messenger', 'telegram' => 'telegram' )[ $cb['network'] ] ?? 'whatsapp';

		if ( '' === $href ) {
			return '';
		}

		return sprintf(
			'<a class="fsi-chat-bubble" href="%1$s" target="_blank" rel="noopener" data-network="%2$s" data-context="chat" style="--fsi-bg:%3$s" aria-label="%4$s">%5$s</a>',
			esc_url( $href ),
			esc_attr( $cb['network'] ),
			esc_attr( $color ),
			esc_attr__( 'Chat with us', 'fuse-social-floating-sidebar' ),
			Fuse_Networks::get_icon_svg( $icon, 26 )
		);
	}
}
