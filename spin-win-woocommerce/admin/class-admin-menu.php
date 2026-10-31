<?php
if (!defined('ABSPATH')) exit;

/**
 * Registers WordPress Admin Menus and Submenus
 */
class Spin_Win_Admin_Menu {
    public function register_menu() {
        // 1. Dedicated Top-Level Menu in WordPress Left Sidebar
        add_menu_page(
            __('Spin & Win for WooCommerce', 'spin-win-woo'),
            __('🎡 Spin & Win', 'spin-win-woo'),
            'manage_woocommerce',
            'spin-win-woo',
            array($this, 'render_dashboard_page'),
            'dashicons-awards',
            56 // Position right below WooCommerce / Products
        );

        add_submenu_page(
            'spin-win-woo',
            __('Slices & Wheel', 'spin-win-woo'),
            __('Wheel & Slices', 'spin-win-woo'),
            'manage_woocommerce',
            'spin-win-woo',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'spin-win-woo',
            __('Security & Settings', 'spin-win-woo'),
            __('Security & Settings', 'spin-win-woo'),
            'manage_woocommerce',
            'spin-win-woo-settings',
            array('Spin_Win_Admin_Settings', 'render')
        );

        add_submenu_page(
            'spin-win-woo',
            __('Spin Logs & Leads', 'spin-win-woo'),
            __('Leads & Spin Logs', 'spin-win-woo'),
            'manage_woocommerce',
            'spin-win-logs',
            array('Spin_Win_Admin_Logs', 'render')
        );

        // 2. Also available under WooCommerce submenu
        add_submenu_page(
            'woocommerce',
            __('Spin & Win', 'spin-win-woo'),
            __('🎡 Spin & Win', 'spin-win-woo'),
            'manage_woocommerce',
            'spin-win-woo-sub',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'woocommerce',
            __('Spin & Win Logs', 'spin-win-woo'),
            __('↳ Spin Logs', 'spin-win-woo'),
            'manage_woocommerce',
            'spin-win-logs-sub',
            array('Spin_Win_Admin_Logs', 'render')
        );
    }

    public function enqueue_styles_and_scripts($hook) {
        if (strpos($hook, 'spin-win') === false) return;
        wp_enqueue_style('spin-win-admin-css', SPIN_WIN_PLUGIN_URL . 'assets/css/spin-wheel.css', array(), SPIN_WIN_VERSION);
        wp_enqueue_style('spin-win-admin-theme-font', 'https://fonts.googleapis.com/css2?family=Permanent+Marker&display=swap', array(), SPIN_WIN_VERSION);
    }

    public function render_dashboard_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'slices';
        ?>
        <div class="wrap spin-win-admin-wrap">
            <h1 style="display:flex;align-items:center;gap:8px;">
                <span>🎡</span> <?php esc_html_e('Spin & Win for WooCommerce', 'spin-win-woo'); ?>
                <span style="font-size:12px;background:#e0e7ff;color:#4338ca;padding:2px 8px;border-radius:12px;font-weight:600;">v1.0.0</span>
            </h1>
            <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=spin-win-woo&tab=slices')); ?>" class="nav-tab <?php echo $active_tab === 'slices' ? 'nav-tab-active' : ''; ?>">
                    🎡 <?php esc_html_e('Wheel Slices & Prizes', 'spin-win-woo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=spin-win-woo-settings')); ?>" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    🛡️ <?php esc_html_e('Security & Anti-Abuse', 'spin-win-woo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=spin-win-logs')); ?>" class="nav-tab">
                    📊 <?php esc_html_e('Leads & Spin Logs', 'spin-win-woo'); ?>
                </a>
            </nav>
            <?php
            if ($active_tab === 'settings') {
                Spin_Win_Admin_Settings::render();
            } else {
                Spin_Win_Admin_Campaigns::render();
            }
            ?>
        </div>
        <?php
    }
}
