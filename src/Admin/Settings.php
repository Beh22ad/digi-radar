<?php
namespace DigiRadar\Admin;

class Settings {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'schedule_price_updater']);
        add_action('digi_radar_daily_update', [$this, 'start_price_update']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_digi_radar_manual_update', [$this, 'manual_update']);
    }

    public function add_admin_menu() {
        // Load SVG icon URL
        $icon_url = digi_radar_get_icon();

        add_menu_page(
            'تنظیمات افزونه دیجی رادار',
            'دیجی رادار',
            'manage_options',
            'digi-radar-settings',
            [$this, 'settings_page'],
            $icon_url 
        );
    }

    public function register_settings() {
        register_setting('digi_radar_settings', 'digi_radar_key');
        register_setting('digi_radar_settings', 'digi_radar_debug', [
            'type' => 'boolean',
            'default' => false,
        ]);
        register_setting('digi_radar_settings', 'digi_radar_unavailable_update', [
            'type' => 'string',
            'default' => 'false',
        ]);
    }

    public function enqueue_scripts($hook) {
        if ($hook === 'toplevel_page_digi-radar-settings') {
            wp_enqueue_script('digi-radar-admin', plugin_dir_url(__DIR__) . '../assets/js/admin.js', ['jquery'], '1.0.0', true);
            wp_localize_script('digi-radar-admin', 'digi_radar_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('digi_radar_manual_update_nonce'),
            ]);
        }
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>تنظیمات افزونه دیجی رادار</h1>

            <form method="post" action="options.php">
                <?php settings_fields('digi_radar_settings'); ?>
                <?php do_settings_sections('digi_radar_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">کلید دسترسی</th>
                        <td>
                            <input type="text" name="digi_radar_key"
                                   value="<?php echo esc_attr(get_option('digi_radar_key')); ?>"
                                   class="regular-text">
                                   <p class="description">
با استفاده از <a href="https://mrnargil.ir/product/digi-radar-membership/">کلید دسترسی</a> به تمام قیمت‌های ارائه شده دسترسی خواهید داشت
                        </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">درصورتی که محصول در دیجیکالا ناموجود بود:</th>
                        <td>
                            <select name="digi_radar_unavailable_update" class="regular-text">
                                <option value="false" <?php selected(get_option('digi_radar_unavailable_update'), 'false'); ?>>
                                    محصول را آپدیت نکن
                                </option>
                                <option value="true" <?php selected(get_option('digi_radar_unavailable_update'), 'true'); ?>>
                                    محصول را ناموجود کن
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">فعالسازی حالت دیباگ</th>
                        <td>
                            <label>
                                <input type="checkbox" name="digi_radar_debug"
                                       value="1" <?php checked(get_option('digi_radar_debug'), 1); ?>>
                                فعال کردن حالت دیباگ
                            </label>
                            <p class="description">
                                در حالت دیباگ، اطلاعات مربوط به بروزرسانی قیمت‌ها در فایل لاگ ثبت می‌شود.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>

            <h2>اجرای دستی ربات</h2>
            <button id="digi-radar-manual-update" class="button button-primary">اجرای ربات</button>
            <p id="digi-radar-update-message" style="display: none;">
                ربات دریافت قیمت با موفقیت اجرا شد، چند لحظه صبر کنید سپس قیمت محصولات سایت را چک کنید.
            </p>
        </div>
        <?php
    }

    public function schedule_price_updater() {
        if ( class_exists( 'ActionScheduler' ) ) {
            // Schedule the action to run every 12 hours if it's not already scheduled
            if ( ! as_next_scheduled_action( 'digi_radar_daily_update' ) ) {
                as_schedule_recurring_action( time(), 12 * HOUR_IN_SECONDS, 'digi_radar_daily_update' );
            }
        }
    }

    public function start_price_update() {
        // Clear the transient to start fresh
        delete_transient('digi_products_ids');

        // Trigger the first AJAX request to start the update process
        wp_remote_post(admin_url('admin-ajax.php'), [
            'blocking' => false,
            'sslverify' => false,
            'headers' => array('X-Requested-With' => 'XMLHttpRequest'),
            'body' => [
                'action' => 'digi_radar_update_product',
                'nonce'  => wp_create_nonce('digi_radar_update_nonce'),
            ],
        ]);
    }

    public function manual_update() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'digi_radar_manual_update_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Clear the transient to start fresh
        delete_transient('digi_products_ids');

        // Trigger the first AJAX request to start the update process
        $response = wp_remote_post(admin_url('admin-ajax.php'), [
            'blocking' => true,
            'sslverify' => false,
            'headers' => array('X-Requested-With' => 'XMLHttpRequest'),
            'body' => [
                'action' => 'digi_radar_update_product',
                '_ajax_nonce' => wp_create_nonce('digi_price'),
            ],
        ]);

        // Debug the response:
        if (is_wp_error($response)) {
            wp_send_json_error('Request failed: ' . $response->get_error_message());
        } else {
             wp_send_json_success('Update started');
        }
    }
}