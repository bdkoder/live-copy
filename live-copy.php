<?php
/**
 * Plugin Name
 *
 * @package           ElementorLiveCopy
 * @author            Shahidul Islam
 * @copyright         2019 Your Name or Company Name
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Elementor Live Copy Tool
 * Plugin URI:        https://github.com/bdkoder
 * Description:       Live Copy for Elementor.
 * Version:           1.0.6
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Shahidul Islam
 * Author URI:        https://github.com/bdkoder
 * Text Domain:       live-copy
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIVE_COPY_VER', '1.0.6' );
define( 'LIVE_COPY__FILE__', __FILE__ );
define( 'LIVE_COPY_URL', plugins_url( '/', LIVE_COPY__FILE__ ) );
define( 'LIVE_COPY_ASSETS_URL', LIVE_COPY_URL . 'assets/' );

require_once dirname( __FILE__ ) . '/includes/class-live-copy.php';

/**
 * Run Live Copy
 *
 * @return void
 */
function run_livecopy() {
	new \ElementorLiveCopy\Live_Copy();
}

run_livecopy();
