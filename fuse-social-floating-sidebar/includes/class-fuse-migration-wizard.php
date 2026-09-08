<?php
/**
 * One-time migration wizard, shown only when a site is upgrading from the
 * legacy Redux-based plugin:
 *
 *   - legacy `fuse` / `fuse_social_options` data exists AND no `_fsi` marker
 *     -> wizard pending (review screen on our settings page + a notice
 *        elsewhere in wp-admin).
 *   - fresh install (no legacy data)      -> never shown.
 *   - `_fsi` already present (migrated)   -> never shown again.
 *
 * The underlying data migration is automatic either way (normalize-on-read),
 * so the site's icons keep rendering during the pending state — the wizard
 * only confirms and persists the imported settings (or starts fresh).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuse_Migration_Wizard {

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_notice' ) );
		add_action( 'admin_post_fuse_skip_migration', array( __CLASS__, 'handle_skip' ) );
	}

	/**
	 * Whether the wizard should be shown.
	 */
	public static function is_pending() {
		$raw = get_option( Fuse_Settings::OPTION_KEY, array() );

		// Already on the new format.
		if ( is_array( $raw ) && isset( $raw[ Fuse_Settings::SUBKEY ] ) && is_array( $raw[ Fuse_Settings::SUBKEY ] ) ) {
			return false;
		}

		// Interim dev builds — treated as already migrated.
		$interim = get_option( 'fuse_social_settings', array() );
		if ( is_array( $interim ) && ! empty( $interim['networks'] ) ) {
			return false;
		}

		return Fuse_Migrator::has_legacy_data();
	}

	/**
	 * Admin-wide pointer (not shown on our own page — the wizard lives there).
	 */
	public static function maybe_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_pending() ) {
			return;
		}
		if ( isset( $_GET['page'] ) && Fuse_Admin_Page::MENU_SLUG === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		?>
		<div class="notice notice-info">
			<p>
				<strong><?php esc_html_e( 'Fuse Social Icons', 'fuse-social-floating-sidebar' ); ?>:</strong>
				<?php esc_html_e( 'We found settings from your previous Fuse Social Floating Sidebar install. Review the import to finish upgrading.', 'fuse-social-floating-sidebar' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Fuse_Admin_Page::MENU_SLUG ) ); ?>"><?php esc_html_e( 'Review migration', 'fuse-social-floating-sidebar' ); ?></a>
			</p>
		</div>
		<?php
	}

	public static function handle_skip() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'fuse-social-floating-sidebar' ) );
		}
		check_admin_referer( 'fuse_skip_migration' );

		// Persist plain defaults under _fsi WITHOUT syncing legacy keys, so the
		// old plugin's stored settings stay untouched (re-import stays possible
		// later from Advanced → Migrate).
		Fuse_Settings::update( Fuse_Settings::defaults(), false );

		wp_safe_redirect( add_query_arg( array( 'page' => Fuse_Admin_Page::MENU_SLUG, 'fresh' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Full-page review screen, rendered in place of the settings tabs.
	 */
	public static function render() {
		// Preview what an import would produce (in-memory, nothing saved yet).
		$raw     = get_option( Fuse_Settings::OPTION_KEY, array() );
		$flat    = get_option( 'fuse_social_options', array() );
		$preview = Fuse_Migrator::normalize( is_array( $raw ) ? $raw : array(), is_array( $flat ) ? $flat : array() );
		$report  = $preview['_migration_report'] ?? array();

		$found_networks = array_filter( $preview['networks'], function ( $n ) {
			return ! empty( $n['url'] );
		} );
		?>
		<div class="fsi-app fsi-wizard-page">
			<div class="fsi-wizard">
				<div class="fsi-mark fsi-wizard-mark"><?php echo Fuse_UI::icon( 'social', 26 ); // phpcs:ignore ?></div>
				<h1><?php esc_html_e( 'Welcome to the new Fuse Social Icons', 'fuse-social-floating-sidebar' ); ?></h1>
				<p class="fsi-wizard-sub"><?php esc_html_e( 'We found settings from your previous version. Your icons are still showing on the site — confirm the import below to finish upgrading.', 'fuse-social-floating-sidebar' ); ?></p>

				<div class="fsi-wizard-stats">
					<div class="fsi-stat"><div class="fsi-sl"><?php esc_html_e( 'Profile links', 'fuse-social-floating-sidebar' ); ?></div><div class="fsi-sv"><?php echo esc_html( number_format_i18n( count( $found_networks ) ) ); ?></div></div>
				</div>

				<?php if ( ! empty( $found_networks ) ) : ?>
					<div class="fsi-wizard-chips">
						<?php foreach ( $found_networks as $row ) :
							$meta = Fuse_Networks::get_profile_network( $row['key'] );
							if ( ! $meta ) {
								continue;
							}
							?>
							<span class="fsi-chip" style="--chip:<?php echo esc_attr( $meta['color'] ); ?>" title="<?php echo esc_attr( $meta['label'] ); ?>"><?php echo Fuse_Networks::get_icon_svg( $row['key'], 15 ); // phpcs:ignore ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $report['notes'] ) ) : ?>
					<ul class="fsi-report fsi-wizard-notes">
						<?php foreach ( $report['notes'] as $note ) : ?>
							<li><?php echo esc_html( $note ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<div class="fsi-wizard-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="fuse_run_migration">
						<?php wp_nonce_field( 'fuse_run_migration' ); ?>
						<button type="submit" class="fsi-btn fsi-btn-accent"><?php echo Fuse_UI::icon( 'check', 16 ); // phpcs:ignore ?> <?php esc_html_e( 'Import my settings', 'fuse-social-floating-sidebar' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="fuse_skip_migration">
						<?php wp_nonce_field( 'fuse_skip_migration' ); ?>
						<button type="submit" class="fsi-btn fsi-btn-ghost"><?php esc_html_e( 'Start fresh instead', 'fuse-social-floating-sidebar' ); ?></button>
					</form>
				</div>
				<p class="fsi-hint"><?php esc_html_e( 'Starting fresh keeps your old plugin data untouched — you can re-import any time from Advanced → Migrate.', 'fuse-social-floating-sidebar' ); ?></p>
			</div>
		</div>
		<?php
	}
}
