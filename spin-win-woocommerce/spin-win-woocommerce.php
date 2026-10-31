<?php
/**
 * Plugin Name: Spin & Win for WooCommerce
 * Plugin URI:  https://epicoptimize.com
 * Description: Standalone, enterprise-grade Lucky Wheel plugin for WooCommerce. Boost conversions with server-side RNG, anti-abuse security, HPOS compatibility, and dynamic coupon auto-apply.
 * Version:     1.0.0
 * Author:      Mohiuddin
 * Author URI:  https://github.com/mohiuddinshanto
 * License:     GPL-2.0-or-later
 * Text Domain: spin-win-woo
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.3
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define Plugin Constants
define('SPIN_WIN_VERSION', '1.0.0');
define('SPIN_WIN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPIN_WIN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPIN_WIN_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Declare HPOS (High-Performance Order Storage) and Cart/Checkout Blocks Compatibility safely
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

/**
 * Check if WooCommerce is installed and active
 */
function spin_win_is_woocommerce_active() {
    return class_exists('WooCommerce') 
        || in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins', array())))
        || (is_multisite() && array_key_exists('woocommerce/woocommerce.php', get_site_option('active_sitewide_plugins', array())));
}

// Include All Plugin Core Files (Safe Require)
$required_files = array(
    'includes/class-loader.php',
    'includes/class-activator.php',
    'includes/class-deactivator.php',
    'includes/class-i18n.php',
    'includes/class-db.php',
    'includes/class-security.php',
    'includes/class-woocommerce-integration.php',
    'includes/class-email.php',
    'includes/class-cron.php',
    'includes/class-rest-api.php',
    'admin/class-admin-menu.php',
    'admin/class-settings.php',
    'admin/class-campaigns.php',
    'admin/class-logs.php',
    'public/class-public.php',
    'public/class-gutenberg.php',
);

foreach ($required_files as $file) {
    $full_path = SPIN_WIN_PLUGIN_DIR . $file;
    if (file_exists($full_path)) {
        require_once $full_path;
    }
}

/**
 * Main Singleton Plugin Execution Class
 */
final class Spin_Win_WooCommerce {
    private static $instance = null;
    protected $loader;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!spin_win_is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        $this->loader = new Spin_Win_Loader();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }

    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php esc_html_e('Spin & Win for WooCommerce requires WooCommerce to be installed and active.', 'spin-win-woo'); ?></p>
        </div>
        <?php
    }

    private function set_locale() {
        if (class_exists('Spin_Win_i18n')) {
            $plugin_i18n = new Spin_Win_i18n();
            $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
        }
    }

    private function define_admin_hooks() {
        if (!is_admin() || !class_exists('Spin_Win_Admin_Menu')) return;
        $admin_menu = new Spin_Win_Admin_Menu();
        $this->loader->add_action('admin_menu', $admin_menu, 'register_menu');
        $this->loader->add_action('admin_enqueue_scripts', $admin_menu, 'enqueue_styles_and_scripts');
    }

    private function define_public_hooks() {
        if (class_exists('Spin_Win_Public')) {
            $public = new Spin_Win_Public();
            $this->loader->add_action('wp_enqueue_scripts', $public, 'enqueue_assets');
            $this->loader->add_action('wp_footer', $public, 'render_popup_wheel', 9999);
            $this->loader->add_action('wp_body_open', $public, 'render_popup_wheel', 9999);
            $this->loader->add_shortcode('spin_win_wheel', $public, 'render_shortcode');
        }
        
        if (class_exists('Spin_Win_WooCommerce_Integration')) {
            $wc = new Spin_Win_WooCommerce_Integration();
            $this->loader->add_action('init', $wc, 'init_sessions_and_endpoints');
            $this->loader->add_filter('woocommerce_account_menu_items', $wc, 'add_my_account_tab');
            $this->loader->add_action('woocommerce_account_winning-coupons_endpoint', $wc, 'render_my_account_coupons');
        }
    }

    private function define_api_hooks() {
        if (class_exists('Spin_Win_Rest_Api')) {
            $api = new Spin_Win_Rest_Api();
            $this->loader->add_action('rest_api_init', $api, 'register_routes');
        }
    }

    public function run() {
        if ($this->loader) {
            $this->loader->run();
        }
    }
}

// Activation and Deactivation Hooks
register_activation_hook(__FILE__, function() {
    if (class_exists('Spin_Win_Activator')) {
        Spin_Win_Activator::activate();
    }
});

register_deactivation_hook(__FILE__, function() {
    if (class_exists('Spin_Win_Deactivator')) {
        Spin_Win_Deactivator::deactivate();
    }
});

// Bootstrap Plugin
add_action('plugins_loaded', function() {
    Spin_Win_WooCommerce::instance()->run();
});
