<?php
/**
 * Plugin Name: Fuse Social Icons
 * Plugin URI: https://www.fusefloat.com/
 * Description: Floating social sidebar, social share buttons, widget, and Gutenberg block — all in one plugin. Rebuilt from the ground up, with a one-click migrator from your existing settings.
 * Version: 6.0.1
 * Author: Daniyal Ahmed
 * Author URI: https://www.fusefloat.com/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fuse-social-floating-sidebar
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FUSE_SOCIAL_ICONS_VERSION', '6.0.1' );
define( 'FUSE_SOCIAL_ICONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FUSE_SOCIAL_ICONS_URL', plugin_dir_url( __FILE__ ) );

// Real Freemius product for this (free) build — the same product the legacy
// free plugin has always used, so existing licenses/opt-ins carry over.
if ( ! defined( 'FUSE_SOCIAL_FS_ID' ) ) {
	define( 'FUSE_SOCIAL_FS_ID', 2701 );
}
if ( ! defined( 'FUSE_SOCIAL_FS_PUBLIC_KEY' ) ) {
	define( 'FUSE_SOCIAL_FS_PUBLIC_KEY', 'pk_70ed0c631ac1720148be7f62dca7e' );
}

require_once FUSE_SOCIAL_ICONS_DIR . 'includes/class-fuse-networks.php';
require_once FUSE_SOCIAL_ICONS_DIR . 'includes/class-fuse-ui.php';
require_once FUSE_SOCIAL_ICONS_DIR . 'includes/class-fuse-pro.php';
require_once FUSE_SOCIAL_ICONS_DIR . 'includes/class-fuse-settings.php';
require_once FUSE_SOCIAL_ICONS_DIR . 'includes/class-fuse-migrator.php';
require_once FUSE_SOCIAL_ICONS_DIR . 'includes/class-fuse-share-links.php';
require_once FUSE_SOCIAL_ICONS_DIR . 'includes/class-fuse-renderer.php';
require_once FUSE_SOCIAL_ICONS_DIR . 'includes/class-fuse-frontend.php';
require_once FUSE_SOCIAL_ICONS_DIR . 'includes/class-fuse-widget.php';
require_once FUSE_SOCIAL_ICONS_DIR . 'includes/class-fuse-shortcode.php';
require_once FUSE_SOCIAL_ICONS_DIR . 'includes/class-fuse-block.php';
require_once FUSE_SOCIAL_ICONS_DIR . 'includes/class-fuse-admin-page.php';
require_once FUSE_SOCIAL_ICONS_DIR . 'includes/class-fuse-migration-wizard.php';

add_action( 'plugins_loaded', 'fuse_social_icons_init' );
function fuse_social_icons_init() {
	load_plugin_textdomain( 'fuse-social-floating-sidebar', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Initialize the Pro/Freemius layer (no-ops safely until a product is configured).
	Fuse_Pro::fs();

	// The Viber share deep link uses its own URL scheme.
	add_filter( 'kses_allowed_protocols', function ( $protocols ) {
		$protocols[] = 'viber';
		return $protocols;
	} );

	Fuse_Frontend::init();
	Fuse_Shortcode::init();
	Fuse_Block::init();
	Fuse_Admin_Page::init();
	Fuse_Migration_Wizard::init();

	add_action( 'widgets_init', array( 'Fuse_Social_Icons_Widget', 'register' ) );
}

