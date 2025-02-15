<?php
/**
 * Plugin Name: دیجی رادار
 * Plugin URI:
 * Description: بروز رسانی اتوماتیک قیمت محصولات از دیجیکالا
 * Version: 1.1.2
 * Author: mrnargil.ir
 * Author URI: https://mrnargil.ir
 * Text Domain: digi-radar
 */

 use DigiRadar\Admin\Settings;
 use DigiRadar\Admin\ProductMeta;
 use DigiRadar\PriceUpdater;
require_once __DIR__ . '/vendor/autoload.php';

// Load SVG icon
function digi_radar_get_icon() {
    return plugin_dir_url(__FILE__) . 'icons/icon.svg';
}

add_action('plugins_loaded', function() {
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
function digi_radar_deactivate() {
    // Check if ActionScheduler is available
    if (class_exists('ActionScheduler')) {
        // Unschedule the recurring action
        as_unschedule_action('digi_radar_daily_update');
    }
}