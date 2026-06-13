<?php
/**
 * Uninstall cleanup — runs only on plugin delete (not deactivate).
 *
 * Data is preserved by default. The history table and options are removed ONLY
 * when `LIVE_COPY_ALLOW_CLEAR` is defined truthy in wp-config.php — the same
 * opt-in that gates the dashboard "Clear data" action — so a careless delete
 * never wipes analytics. Runs in a bare context (plugin classes NOT loaded), so
 * names are hardcoded to match Live_Copy_DB / Live_Copy_Settings.
 *
 * @package ElementorLiveCopy
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Always clear the scheduled cron — it is not user data and is useless once the
// plugin is gone.
wp_clear_scheduled_hook( 'live_copy_prune_history' );

// Destructive removal is opt-in only.
if ( ! ( defined( 'LIVE_COPY_ALLOW_CLEAR' ) && LIVE_COPY_ALLOW_CLEAR ) ) {
	return;
}

// Delete options.
delete_option( 'live_copy_settings' );
delete_option( 'live_copy_table_version' );

// Drop the history table.
global $wpdb;
$table = $wpdb->prefix . 'live_copy_history';
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
