<?php
/**
 * Registers the fuse/social-icons Gutenberg block (plain JS, no build step).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuse_Block {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'fuse-social-icons-block',
			FUSE_SOCIAL_ICONS_URL . 'blocks/social-icons/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			FUSE_SOCIAL_ICONS_VERSION,
			true
		);

		register_block_type( FUSE_SOCIAL_ICONS_DIR . 'blocks/social-icons' );
	}
}
