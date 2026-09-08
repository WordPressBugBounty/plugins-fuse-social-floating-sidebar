<?php
/**
 * Pro tier gating, backed by Freemius.
 *
 * This is the FREE build. FUSE_SOCIAL_FS_ID / FUSE_SOCIAL_FS_PUBLIC_KEY are
 * defined in the main plugin file using the same Freemius product the legacy
 * free plugin has always shipped with (is_premium: false), so this is a
 * genuine license check, not a placeholder.
 *
 * For local development you can preview the Pro UI without a real license by
 * defining FUSE_SOCIAL_PRO_PREVIEW as true (e.g. in wp-config.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuse_Pro {

	private static $fs = null;
	private static $initialized = false;

	/**
	 * Whether Freemius credentials have been configured for this install.
	 */
	public static function is_configured() {
		return defined( 'FUSE_SOCIAL_FS_ID' ) && (int) FUSE_SOCIAL_FS_ID > 0
			&& defined( 'FUSE_SOCIAL_FS_PUBLIC_KEY' ) && FUSE_SOCIAL_FS_PUBLIC_KEY;
	}

	/**
	 * Lazily initialize the Freemius SDK. No-ops (safely) when the product
	 * isn't configured yet, so a placeholder never triggers network calls.
	 */
	public static function fs() {
		if ( self::$initialized ) {
			return self::$fs;
		}
		self::$initialized = true;

		if ( ! self::is_configured() ) {
			return null;
		}

		if ( ! file_exists( FUSE_SOCIAL_ICONS_DIR . 'freemius/start.php' ) ) {
			return null;
		}

		require_once FUSE_SOCIAL_ICONS_DIR . 'freemius/start.php';

		self::$fs = fs_dynamic_init( array(
			'id'                  => (int) FUSE_SOCIAL_FS_ID,
			'slug'                => 'fuse-social-floating-sidebar',
			'type'                => 'plugin',
			'public_key'          => FUSE_SOCIAL_FS_PUBLIC_KEY,
			'is_premium'          => defined( 'FUSE_SOCIAL_IS_PREMIUM' ) && FUSE_SOCIAL_IS_PREMIUM,
			'premium_suffix'      => 'FUSE PRO',
			'has_premium_version' => true,
			'has_addons'          => false,
			'has_paid_plans'      => true,
			'is_live'             => true,
			// Top-level menu (add_menu_page, not a Settings submenu) — no
			// 'parent' key, so Freemius nests its own pages under this menu.
			'menu'                => array(
				'slug' => Fuse_Admin_Page::MENU_SLUG,
			),
		) );

		return self::$fs;
	}

	/**
	 * The single gate every Pro feature checks.
	 */
	public static function is_pro() {
		if ( defined( 'FUSE_SOCIAL_PRO_PREVIEW' ) && FUSE_SOCIAL_PRO_PREVIEW ) {
			return true;
		}

		$fs  = self::fs();
		$pro = $fs ? $fs->can_use_premium_code() : false;

		/**
		 * Filter whether Pro features are unlocked.
		 *
		 * @param bool $pro
		 */
		return (bool) apply_filters( 'fuse_social_is_pro', $pro );
	}

	/**
	 * URL to send users to for upgrading. Falls back to the plugin site when
	 * Freemius isn't configured.
	 */
	public static function upgrade_url() {
		$fs = self::fs();
		if ( $fs && method_exists( $fs, 'get_upgrade_url' ) ) {
			return $fs->get_upgrade_url();
		}
		return 'https://www.fusefloat.com/pricing/';
	}
}
