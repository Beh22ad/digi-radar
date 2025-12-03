<?php

/**
 * Plugin Name: دیجی رادار
 * Plugin URI:
 * Description: بروز رسانی اتوماتیک قیمت محصولات از دیجیکالا
 * Version: 1.3.2
 * Author: mrnargil.ir
 * Author URI: https://mrnargil.ir
 * Text Domain: digi-radar
 */

use DigiRadar\Admin\Settings;
use DigiRadar\Admin\ProductMeta;
use DigiRadar\PriceUpdater;

require_once __DIR__ . '/vendor/autoload.php';

// Define the main plugin file path
if (!defined("DIGI_RADAR_MAIN_FILE")) {
    define("DIGI_RADAR_MAIN_FILE", __FILE__);
}

// delete update catch after upgrade
add_action('upgrader_process_complete', function ($upgrader, $options) {
    // Get namespace from header
    $plugin_data = get_file_data(DIGI_RADAR_MAIN_FILE, [
        'TextDomain' => 'Text Domain',
    ]);
    $namespace = $plugin_data['TextDomain'];

    // Delete transient
    delete_transient($namespace . '_update_response');
}, 10, 2);

// Load SVG icon
function digi_radar_get_icon()
{
    return plugin_dir_url(__FILE__) . 'icons/icon.svg';
}

add_action('plugins_loaded', function () {
    // Initialize plugin components
    Settings::get_instance();
    ProductMeta::get_instance();
    PriceUpdater::get_instance();
});

// Register deactivation hook
register_deactivation_hook(__FILE__, 'digi_radar_deactivate');

/**
 * Function to run when the plugin is deactivated.
 */
function digi_radar_deactivate()
{
    // Check if ActionScheduler is available
    if (class_exists('ActionScheduler')) {
        // Unschedule the recurring action
        as_unschedule_action('digi_radar_daily_update');
    }
}
