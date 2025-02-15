<?php
namespace DigiRadar\Admin;

class ProductMeta {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_product_tab_content']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_meta']);
        add_action('admin_head', [$this, 'custom_admin_css']);
        add_shortcode('digi-price-date', [$this, 'digi_radar_date_shortcode']);
    }

    public function add_product_tab($tabs) {
        $tabs['digi_price'] = [
            'label'    => 'دیجی رادار',
            'target'   => 'digi_radar_data',
            'class'    => array( '' ),
            'priority' => 100,
        ];
        return $tabs;
    }

    public function add_product_tab_content() {
        global $post;
        ?>
        <div id="digi_radar_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                // Checkbox for auto-update
                woocommerce_wp_checkbox([
                    'id'          => '_digi_radar_auto_update',
                    'label'       => 'بروز رسانی:',
                    'description' => 'قیمت این محصول به طور اتوماتیک آپدیت شود',
                    'desc_tip'    => false,
                ]);

                // Product code input
                woocommerce_wp_text_input([
                    'id'    => '_digi_product_code',
                    'label' => 'کد محصول:',
                    'type'  => 'text',
                    'class' => 'ltr-input',
                    'description' => '<a href="https://mrnargil.ir/product/digi-radar-membership/">لیست محصولات موجود</a>',
                ]);

                // Price adjustment input (formula)
                woocommerce_wp_text_input([
                    'id'          => '_digi_radar_adjustment',
                    'label'       => 'اصلاح قیمت:',
                    'type'        => 'text',
                    'class'       => 'ltr-input',
                    'placeholder' => '{price}',
                    'description' => 'در اینجا میتوانید قیمت دریافت شده را کم یا زیاد کنید مثلا:<br>
                                     <code>{price}*1.1</code> قیمت دریافتی را ده درصد زیاد میکند.<br>',
                    'desc_tip'    => false,
                ]);

                // Last price date (disabled)
                woocommerce_wp_text_input([
                    'id'    => '_digi_radar_last_price_date',
                    'label' => 'قیمت برای:',
                    'type'  => 'text',
                    'description' => 'شورتکد تاریخ:<code>[digi-price-date code=""]</code>',
                    'disabled' => true,
                    'class' => 'ltr-input',
                ]);

                // Next update date (disabled)
                woocommerce_wp_text_input([
                    'id'    => '_digi_radar_next_update',
                    'label' => 'تاریخ اجرای ربات:',
                    'type'  => 'text',
                    'disabled' => true,
                    'class' => 'ltr-input',
                ]);
                ?>
            </div>
        </div>
        <?php
    }

    public function save_product_meta($post_id) {
        // Save auto-update checkbox
        $auto_update = isset($_POST['_digi_radar_auto_update']) ? 'yes' : 'no';
        update_post_meta($post_id, '_digi_radar_auto_update', $auto_update);

        // Save product code
        if (isset($_POST['_digi_product_code'])) {
            update_post_meta($post_id, '_digi_product_code', sanitize_text_field($_POST['_digi_product_code']));
        }

        // Save price adjustment formula
        if (isset($_POST['_digi_radar_adjustment'])) {
            update_post_meta($post_id, '_digi_radar_adjustment', sanitize_text_field($_POST['_digi_radar_adjustment']));
        }
    }



    /**
     * Shortcode to display the last price date.
     *
     * @param array $atts Shortcode attributes.
     * @return string The last price date.
     */
    public function digi_radar_date_shortcode($atts) {
        $atts = shortcode_atts([
            'code' => '', // Default code is empty
        ], $atts, 'digi-price-date');

        // If on a single product page, ignore the code attribute and get the current product's last price date
        if (is_product()) {
            global $post;
            $product_id = $post->ID;
            $last_price_date = get_post_meta($product_id, '_digi_radar_last_price_date', true);
            return '<p class="digi-price-update-date">'.$last_price_date.'</p>';
        }

        // If not on a single product page, use the code attribute to find the product
        if (!empty($atts['code'])) {
            $product_id = $this->get_product_id_by_code($atts['code']);
            if ($product_id) {
                $last_price_date = get_post_meta($product_id, '_digi_radar_last_price_date', true);
                return '<p class="digi-price-update-date">'.$last_price_date.'</p>';
            }
        }

        return '<p class="digi-price-update-date">نامشخص</p>';
    }

    /**
     * Get product ID by product code.
     *
     * @param string $code The product code.
     * @return int|false The product ID or false if not found.
     */
    private function get_product_id_by_code($code) {
        $args = [
            'post_type'  => 'product',
            'meta_key'   => '_digi_product_code',
            'meta_value' => $code,
            'fields'     => 'ids',
            'posts_per_page' => 1,
        ];

        $products = get_posts($args);

        if (!empty($products)) {
            return $products[0];
        }

        return false;
    }


    public function custom_admin_css() {
        ?>
        <style>
            /* Make input fields LTR */
            .ltr-input input {
                direction: ltr;
                text-align: left;
            }

            /* Add some spacing for the checkbox label */
            .options_group .checkbox {
                margin-bottom: 10px;
            }

            /* Style for the formula input and description */
            #_digi_radar_adjustment {
                font-family: monospace;
            }

            .options_group .description {
                margin-top: 10px;
                display: block;
                font-size: 13px;
                color: #666;
            }

            .options_group .description code {
                background: #f7f7f7;
                padding: 2px 4px;
                border-radius: 3px;
                font-family: monospace;
            }
            .ltr-input {
                direction: ltr;
                width: 80% !important;
            }
            li.digi_price_options.digi_price_tab a::before {
                content: "\f137" !important;
            }
        </style>
        <?php
    }



}
