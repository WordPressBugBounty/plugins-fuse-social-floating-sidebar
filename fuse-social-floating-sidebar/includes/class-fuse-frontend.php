<?php
/**
 * Wires the renderer into WordPress on the front end: floating bar
 * (wp_footer), auto-injected share buttons (the_content), custom CSS
 * (wp_head), and asset enqueueing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuse_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_head_css' ), 99 );
		add_action( 'wp_footer', array( __CLASS__, 'render_footer' ), 100 );
		add_filter( 'the_content', array( __CLASS__, 'inject_share_buttons' ) );
	}

	public static function enqueue_assets() {
		wp_enqueue_style( 'fuse-social-floating-sidebar', FUSE_SOCIAL_ICONS_URL . 'assets/css/frontend.css', array(), FUSE_SOCIAL_ICONS_VERSION );
		wp_enqueue_script( 'fuse-social-floating-sidebar', FUSE_SOCIAL_ICONS_URL . 'assets/js/frontend.js', array(), FUSE_SOCIAL_ICONS_VERSION, true );
	}

	/**
	 * User custom CSS, inlined in the head.
	 */
	public static function print_head_css() {
		$settings = Fuse_Settings::get();

		$css = (string) ( $settings['advanced']['custom_css'] ?? '' );

		if ( '' !== trim( $css ) ) {
			echo "\n<style id=\"fuse-social-icons-inline\">" . wp_strip_all_tags( $css ) . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	public static function render_footer() {
		$settings = Fuse_Settings::get();

		// Floating bar.
		if ( ! empty( $settings['floating']['enabled'] ) ) {
			echo Fuse_Renderer::render_profile_icons( $settings, array( 'wrap_id' => 'fsi-floating-bar', 'mode' => 'floating' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	public static function inject_share_buttons( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$settings = Fuse_Settings::get();
		$s        = $settings['share'];

		if ( empty( $s['enabled'] ) || ( empty( $s['before'] ) && empty( $s['after'] ) ) ) {
			return $content;
		}

		$html = Fuse_Renderer::render_share_buttons( $settings, get_permalink(), get_the_title() );
		if ( '' === $html ) {
			return $content;
		}

		$before = ! empty( $s['before'] ) ? $html : '';
		$after  = ! empty( $s['after'] ) ? $html : '';

		return $before . $content . $after;
	}
}
