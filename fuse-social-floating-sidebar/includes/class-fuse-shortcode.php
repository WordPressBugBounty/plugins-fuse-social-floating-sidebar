<?php
/**
 * [fuse_icons] and [fuse_share] shortcodes, for theme templates and
 * page-builder embedding.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuse_Shortcode {

	public static function init() {
		add_shortcode( 'fuse_icons', array( __CLASS__, 'render_icons' ) );
		add_shortcode( 'fuse_share', array( __CLASS__, 'render_share' ) );
	}

	public static function render_icons( $atts ) {
		$settings = Fuse_Settings::get();
		return Fuse_Renderer::render_profile_icons( $settings, array( 'mode' => 'inline', 'wrap_class' => 'fsi-shortcode' ) );
	}

	public static function render_share( $atts ) {
		$settings = Fuse_Settings::get();
		$settings['share']['enabled'] = true; // explicit shortcode always renders.
		$url   = is_singular() ? get_permalink() : home_url( '/' );
		$title = is_singular() ? get_the_title() : get_bloginfo( 'name' );

		return Fuse_Renderer::render_share_buttons( $settings, $url, $title, array( 'wrap_class' => 'fsi-shortcode' ) );
	}
}
