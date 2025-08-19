<?php
/**
 * Plugin Name:       Live Copy Paste – Ultimate Elementor Cross-Domain Design Transfer
 * Description:       Live Copy Paste for Elementor. Copy and paste any element from one site to another site.
 * Version:           1.0.13
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

add_action('plugin_loaded', function () {
});

define( 'LIVE_COPY_VER', '1.0.13' );
define( 'LIVE_COPY__FILE__', __FILE__ );
define( 'LIVE_COPY_URL', plugins_url( '/', LIVE_COPY__FILE__ ) );
define( 'LIVE_COPY_ASSETS_URL', LIVE_COPY_URL . 'assets/' );

require_once dirname( __FILE__ ) . '/includes/class-live-copy.php';

/**
 * Run Live Copy
 *
 * @return void
 */

add_action('wp', function () {
	if ( defined( 'SKY_ADDONS_SITE' ) ) {
		if ( is_home() ) {
			return;
		}

		if ( is_page( [ 6, 205, 'elementor-widgets', 'elementor-templates', 'pricing', 'roadmaps', 'changelog' ] ) ) {
			return;
		}
	}

	// Skip in admin area
	if ( is_admin() ) {
		return;
	}

	\ElementorLiveCopy\Live_Copy::enqueue_assets();
});


// Initialize Live Copy
new \ElementorLiveCopy\Live_Copy();
