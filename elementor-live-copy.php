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
 * Plugin Name:       Live Copy
 * Plugin URI:        https://example.com/
 * Description:       Live Copy for Elementor.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Shahidul Islam
 * Author URI:        https://github.com/bdkoder
 * Text Domain:       live-copy
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://example.com/my-plugin/
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

define('LIVE_COPY_VER', '1.0.0');
define('LIVE_COPY__FILE__', __FILE__);
define('LIVE_COPY_URL', plugins_url('/', LIVE_COPY__FILE__));
define('LIVE_COPY_ASSETS_URL', LIVE_COPY_URL . 'assets/');

include_once dirname(__FILE__) . '/includes/class-live-copy.php';

function run_livecopy() {
    new \ElementorLiveCopy\LiveCopy();
}

run_livecopy();
