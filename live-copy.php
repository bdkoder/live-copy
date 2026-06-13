<?php
/**
 * Plugin Name:       Live Copy & Download for Elementor – Cross-Domain Design Transfer
 * Description:       Copy or download any Elementor section as JSON and paste it on another site. Frontend buttons, role-based access, and copy analytics dashboard.
 * Version:           1.1.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Shahidul Islam
 * Author URI:        https://github.com/bdkoder
 * Text Domain:       live-copy
 * License:           GPL v3 or later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIVE_COPY_VER',        '1.1.0' );
define( 'LIVE_COPY__FILE__',    __FILE__ );
define( 'LIVE_COPY_PATH',       plugin_dir_path( __FILE__ ) );
define( 'LIVE_COPY_URL',        plugins_url( '/', __FILE__ ) );
define( 'LIVE_COPY_ASSETS_URL', LIVE_COPY_URL . 'assets/' );
define( 'LIVE_COPY_ADMIN_URL',  LIVE_COPY_URL . 'admin/build/' );

require_once LIVE_COPY_PATH . 'includes/class-live-copy-db.php';
require_once LIVE_COPY_PATH . 'includes/class-live-copy-settings.php';
require_once LIVE_COPY_PATH . 'includes/class-live-copy-rest.php';
require_once LIVE_COPY_PATH . 'includes/class-live-copy.php';

// Create DB table + schedule pruning cron on activation.
register_activation_hook( __FILE__, function () {
	\ElementorLiveCopy\Live_Copy_DB::create_table();
	if ( ! wp_next_scheduled( \ElementorLiveCopy\Live_Copy_DB::CRON_HOOK ) ) {
		wp_schedule_event( time(), 'daily', \ElementorLiveCopy\Live_Copy_DB::CRON_HOOK );
	}
} );

// Clear the cron on deactivation (table is kept; data preserved).
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( \ElementorLiveCopy\Live_Copy_DB::CRON_HOOK );
} );

// Daily history pruning.
add_action( \ElementorLiveCopy\Live_Copy_DB::CRON_HOOK, [ \ElementorLiveCopy\Live_Copy_DB::class, 'prune' ] );

// Upgrade check on every load (cheap option-compare).
add_action( 'plugins_loaded', [ \ElementorLiveCopy\Live_Copy_DB::class, 'maybe_upgrade' ] );

// REST API routes.
\ElementorLiveCopy\Live_Copy_Rest::init();

// Admin: settings page + asset enqueue.
add_action( 'admin_menu',            [ \ElementorLiveCopy\Live_Copy_Settings::class, 'register_admin_page' ] );
add_action( 'admin_enqueue_scripts', [ \ElementorLiveCopy\Live_Copy_Settings::class, 'enqueue_admin_assets' ] );

// Front-end: enqueue assets.
add_action( 'wp', function () {
	if ( is_admin() ) {
		return;
	}

	/**
	 * Filter whether Live Copy assets load on the current request.
	 *
	 * Return false to disable the copy/download buttons on specific pages or
	 * contexts. Example — disable on the blog home and a few pages:
	 *
	 *     add_filter( 'live_copy_should_load', function ( $load ) {
	 *         if ( is_home() || is_page( [ 'pricing', 'changelog' ] ) ) {
	 *             return false;
	 *         }
	 *         return $load;
	 *     } );
	 *
	 * @param bool $load Whether to load. Default true.
	 */
	if ( ! apply_filters( 'live_copy_should_load', true ) ) {
		return;
	}

	\ElementorLiveCopy\Live_Copy::enqueue_assets();
} );

// Instantiate always so AJAX hooks register (admin-ajax.php never fires the 'wp' action).
new \ElementorLiveCopy\Live_Copy();
