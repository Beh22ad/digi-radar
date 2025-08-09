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

        // Hooks for variable products
        add_action('woocommerce_product_after_variable_attributes', [$this, 'add_variation_fields'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'save_variation_fields'], 10, 2);
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
        $product = wc_get_product($post->ID);
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
                    'wrapper_class' => 'form-row-full',
                ]);

                if (!$product->is_type('variable')) {
                    // Product code input
                    woocommerce_wp_text_input([
                        'id'    => '_digi_product_code',
                        'label' => 'کد محصول:',
                        'type'  => 'text',
                        'class' => 'ltr-input',
                        'description' => '<a href="https://mrnargil.ir/product/digi-radar-membership/">لیست محصولات موجود</a>',
                        'desc_tip'    => false,
                        'wrapper_class' => 'form-row-full',
                    ]);

                    // Price adjustment input (formula)
                    woocommerce_wp_text_input([
                        'id'          => '_digi_radar_adjustment',
                        'label'       => 'اصلاح قیمت:',
                        'type'        => 'text',
                        'class'       => 'ltr-input',
                        'placeholder' => '{price}',
                        'description' => 'در اینجا میتوانید قیمت دریافت شده را کم یا زیاد کنید مثلا:<br>
                                         <code>{price}*1.1</code> قیمت دریافتی را ده درصد زیاد میکند.',
                        'desc_tip'    => false,
                        'wrapper_class' => 'form-row-full',
                    ]);

                    // Last price date (disabled)
                    woocommerce_wp_text_input([
                        'id'    => '_digi_radar_last_price_date',
                        'label' => 'قیمت برای:',
                        'type'  => 'text',
                        'description' => 'شورتکد تاریخ:<code>[digi-price-date code=""]</code>',
                        'disabled' => true,
                        'class' => 'ltr-input',
                        'desc_tip'    => false,
                        'wrapper_class' => 'form-row-full',
                    ]);

                    // Next update date (disabled)
                    woocommerce_wp_text_input([
                        'id'    => '_digi_radar_next_update',
                        'label' => 'تاریخ اجرای ربات:',
                        'type'  => 'text',
                        'disabled' => true,
                        'class' => 'ltr-input',
                        'wrapper_class' => 'form-row-full',
                    ]);
                } else {
                    echo '<p>برای انجام تنظیمات هر محصول، به تب متغییرها مراجعه کنید.</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    public function add_variation_fields($loop, $variation_data, $variation) {
        $product_code = get_post_meta($variation->ID, '_digi_product_code', true);
        $price_adjustment = get_post_meta($variation->ID, '_digi_radar_adjustment', true);
        $last_price_date = get_post_meta($variation->ID, '_digi_radar_last_price_date', true);

        echo '<div class="form-row form-row-full">';
        echo '<h3 style="    padding: 0px !important;
    margin-top: 35px !important;">تنظیمات دیجی رادار</h3>';
        
        woocommerce_wp_text_input([
            'id'    => "_digi_product_code[$loop]",
            'label' => 'کد محصول:',
            'type'  => 'text',
            'class' => 'ltr-input',
            'value' => $product_code,
            'description' => '<a href="https://mrnargil.ir/product/digi-radar-membership/">لیست محصولات موجود</a>',
            'desc_tip' => false,
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'          => "_digi_radar_adjustment[$loop]",
            'label'       => 'اصلاح قیمت:',
            'type'        => 'text',
            'class'       => 'ltr-input',
            'placeholder' => '{price}',
            'value'       => $price_adjustment,
            'description' => 'در اینجا میتوانید قیمت دریافت شده را کم یا زیاد کنید.
                <br>
                <code>{price}*1.1</code> قیمت دریافتی را ده درصد زیاد میکند.',
            'desc_tip'    => false,
            'wrapper_class' => 'form-row form-row-full',
        ]);

        woocommerce_wp_text_input([
            'id'    => "_digi_radar_last_price_date[$loop]",
            'label' => 'قیمت برای:',
            'type'  => 'text',
            'disabled' => true,
            'value' => $last_price_date,
            'class' => 'ltr-input',
            'description' => 'شورتکد تاریخ:<code>[digi-price-date code=""]</code>',
            'desc_tip' => false,
            'wrapper_class' => 'form-row form-row-full',
        ]);

        echo '</div>';
    }

    public function save_product_meta($post_id) {
        $product = wc_get_product($post_id);

        if (!$product->is_type('variable')) {
            $auto_update = isset($_POST['_digi_radar_auto_update']) ? 'yes' : 'no';
            update_post_meta($post_id, '_digi_radar_auto_update', $auto_update);

            if (isset($_POST['_digi_product_code'])) {
                update_post_meta($post_id, '_digi_product_code', sanitize_text_field($_POST['_digi_product_code']));
            }

            if (isset($_POST['_digi_radar_adjustment'])) {
                update_post_meta($post_id, '_digi_radar_adjustment', sanitize_text_field($_POST['_digi_radar_adjustment']));
            }
        } else {
            $auto_update = isset($_POST['_digi_radar_auto_update']) ? 'yes' : 'no';
            update_post_meta($post_id, '_digi_radar_auto_update', $auto_update);
        }
    }

    public function save_variation_fields($variation_id, $loop) {
        if (isset($_POST['_digi_product_code'][$loop])) {
            update_post_meta($variation_id, '_digi_product_code', sanitize_text_field($_POST['_digi_product_code'][$loop]));
        }

        if (isset($_POST['_digi_radar_adjustment'][$loop])) {
            update_post_meta($variation_id, '_digi_radar_adjustment', sanitize_text_field($_POST['_digi_radar_adjustment'][$loop]));
        }
    }

    public function digi_radar_date_shortcode($atts) {
        $atts = shortcode_atts([
            'code' => '',
        ], $atts, 'digi-price-date');

        if (is_product()) {
            global $post;
            $product = wc_get_product($post->ID);
            if ($product->is_type('variable')) {
                $variations = $product->get_children();
                foreach($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation->is_purchasable()) {
                        $last_price_date = get_post_meta($variation_id, '_digi_radar_last_price_date', true);
                        return '<p class="digi-price-update-date">'.$last_price_date.'</p>';
                    }
                }
            } else {
                $last_price_date = get_post_meta($post->ID, '_digi_radar_last_price_date', true);
                return '<p class="digi-price-update-date">'.$last_price_date.'</p>';
            }
        }

        if (!empty($atts['code'])) {
            $product_id = $this->get_product_id_by_code($atts['code']);
            if ($product_id) {
                $last_price_date = get_post_meta($product_id, '_digi_radar_last_price_date', true);
                return '<p class="digi-price-update-date">'.$last_price_date.'</p>';
            }
        }

        return '<p class="digi-price-update-date">نامشخص</p>';
    }

    private function get_product_id_by_code($code) {
        $args = [
            'post_type'  => ['product', 'product_variation'],
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

    <?php
}

}