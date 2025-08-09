<?php
namespace DigiRadar;
use DigiRadar\Admin\DateConverter;

class PriceUpdater {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_digi_radar_update_product', [$this, 'update_product']);
        add_action('wp_ajax_nopriv_digi_radar_update_product', [$this, 'update_product']);
    }

    public function update_product() {
        // Verify nonce
       // check_ajax_referer('digi_price',  '_ajax_nonce');

        // Get product IDs
        $product_ids = get_transient('digi_products_ids');
        if (false === $product_ids) {
            // Get all products with auto-update enabled
            $product_ids = get_posts([
                'post_type'      => 'product',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'   => '_digi_radar_auto_update',
                        'value' => 'yes',
                    ],
                ],
            ]);
            set_transient('digi_products_ids', $product_ids, HOUR_IN_SECONDS);
        }

        if (empty($product_ids)) {
            // Delete all transients when no products are left
            $this->delete_all_transients();
            $this->log('All products updated and transients deleted');
            wp_send_json_success('All products updated and transients deleted');
        }

        // Process the first product
        $product_id = array_shift($product_ids);
        $this->process_product($product_id);

        // Update the transient with remaining product IDs
        set_transient('digi_products_ids', $product_ids, HOUR_IN_SECONDS);

        // If there are more products, trigger the next AJAX request
        if (!empty($product_ids)) {
            wp_remote_post(admin_url('admin-ajax.php'), [
                'blocking' => false,
                'sslverify' => false,
                'headers' => array('X-Requested-With' => 'XMLHttpRequest'),
                'body' => [
                    'action' => 'digi_radar_update_product',
                    'nonce'  => wp_create_nonce('digi_radar_update_nonce'),
                ],
            ]);
        } else {
            // Delete all transients when all products are processed
            $this->delete_all_transients();
        }

        wp_send_json_success('Product updated');
    }

    private function process_product($product_id) {
        $product = wc_get_product($product_id);

        if (!$product) {
            $this->log("Product {$product_id} not found.");
            return;
        }

        if ($product->is_type('variable')) {
            $this->log("Processing variable product {$product_id}");
            $variations = $product->get_children();
            $parent_in_stock = false;

            foreach ($variations as $variation_id) {
                // Process each variation as a single item
                $this->process_single_item($variation_id);
                $variation = wc_get_product($variation_id);
                if ($variation && $variation->is_in_stock()) {
                    $parent_in_stock = true;
                }
            }

            // Update parent product stock status
            $parent_stock_status = $parent_in_stock ? 'instock' : 'outofstock';
            $product->set_stock_status($parent_stock_status);
            $product->save();
            wc_delete_product_transients($product_id);

        } else {
            // Process simple product
            $this->log("Processing simple product {$product_id}");
            $this->process_single_item($product_id);
        }
    }

    private function process_single_item($item_id) {
        $product_code = get_post_meta($item_id, '_digi_product_code', true);
        if (empty($product_code)) {
            $this->log("Product {$item_id} skipped: No product code found");
            return;
        }

        // Extract the base code (e.g., "loole" from "loole_8")
        $base_code = preg_replace('/_\d+$/', '', $product_code);

        // Prefix the transient name with 'digi_radar_'
        $transient_name = 'digi_radar_' . $base_code;

        // Get cached data or fetch from API
        $data = get_transient($transient_name);
        if (false === $data) {
            $api_key = get_option('digi_radar_key');
            $api_url = "https://digikala-api.maya1535.workers.dev/?auth={$api_key}&id={$base_code}";
            $response = wp_remote_get($api_url);
            $this->log(print_r('url= '.$api_url, true));
            if (is_wp_error($response)) {
                $this->log("API request failed for product {$item_id}: " . $response->get_error_message());
                return;
            }

            $data = wp_remote_retrieve_body($response);
            set_transient($transient_name, $data, HOUR_IN_SECONDS);
        }

        $data = json_decode($data, true);
        if ($data['status'] !== 'ok') {
            $this->log("API response status is not OK for product {$item_id}");
            return;
        }

        $currency = get_woocommerce_currency();
        // Update next update date using Persian calendar and Iran time
        $jalaliDate = DateConverter::gregorianToJalali(date('Y'), date('m'), date('d'), '/');
        date_default_timezone_set('Asia/Tehran');
        $iranTime = date('H:i');
        $next_update_date = $jalaliDate." ($iranTime)"; // Persian date format

        $in_stock = false;
        $found_price = false;

        // Find the matching product in the API response
        foreach ($data['data'] as $item) {
            if ($item['id'] === $product_code) {
                $in_stock = true;
                $found_price = true;
                // Access the price and last price date using Persian keys
                $api_price = $item["price"]; 
                $api_sale_price = $item['sale_price']; 

                if ($currency === 'IRT') {
					$api_price = $api_price / 10;
                    $api_sale_price = $api_sale_price / 10;
				}

                $last_price_date =  $item['date']; // تاریخ اخرین قیمت

                // Calculate the adjusted price
                $price_adjustment = get_post_meta($item_id, '_digi_radar_adjustment', true);
                if (!empty($price_adjustment)) {
                    $adjusted_price =  eval('return ' . str_replace('{price}', $api_price, $price_adjustment) . ';');
                    $adjusted_sale_price =  eval('return ' . str_replace('{price}', $api_sale_price, $price_adjustment) . ';');  
                } else {
                    $adjusted_price = $api_price;
                    $adjusted_sale_price = $api_sale_price;
                }

                $product = wc_get_product($item_id);
                $product->set_stock_status('instock');

                if ($adjusted_price == $adjusted_sale_price) {
                    // Update regular price and remove sale price
                    $product->set_regular_price($adjusted_price);
                    $product->set_sale_price('');
                } else {
                    // Keep regular price and update sale price
                    $product->set_regular_price($adjusted_price);
                    $product->set_sale_price($adjusted_sale_price);
                }
                
                $product->save();
                wc_delete_product_transients($item_id);

                // Update last price date
                update_post_meta($item_id, '_digi_radar_last_price_date', $last_price_date);
                update_post_meta($item_id, '_digi_radar_next_update', $next_update_date);

                $this->log("Product {$item_id} updated: Price = {$adjusted_price}, Last Price Date = {$last_price_date}, Next Update Date = {$next_update_date}");
                break;
            }
        }
        
        // If price was not found and unavailable update is enabled
        $update_unavailable = get_option('digi_radar_unavailable_update', 'false');
        if ( !$found_price && $update_unavailable === 'true' ) {
            $product = wc_get_product($item_id);
            $product->set_stock_status('outofstock');
            if ($product->managing_stock()) {
               // $product->set_stock_quantity(0);
            }               
            $product->save();
            wc_delete_product_transients($item_id);            
            update_post_meta($item_id, '_digi_radar_next_update', $next_update_date);
            $this->log("Product {$item_id} updated: is out of stock");
        }
    }


    private function delete_all_transients() {
    global $wpdb;

    // Delete the main transient
    delete_transient('digi_products_ids');

    // Delete all transients prefixed with 'digi_radar_'
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_digi_radar_%'
        )
    );

    // Log the deletion
    $this->log('All transients deleted');
}

    private function log($message) {
        if (get_option('digi_radar_debug')) {
            error_log("[digi-price Price Plugin] {$message}");
        }
    }
}