<?php
/**
 * Uninstall cleanup. Only runs when the plugin is deleted via the WP admin.
 *
 * IMPORTANT: the `fuse` option row is SHARED with the legacy "Fuse Social
 * Floating Sidebar" (free/premium) plugin, so we only remove our own `_fsi`
 * subkey from it — never the whole option, and never the legacy keys.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$fuse = get_option( 'fuse', array() );
if ( is_array( $fuse ) && isset( $fuse['_fsi'] ) ) {
	unset( $fuse['_fsi'] );
	if ( empty( $fuse ) ) {
		delete_option( 'fuse' );
	} else {
		update_option( 'fuse', $fuse );
	}
}

delete_transient( 'fuse_social_icons_migration_notice' );
