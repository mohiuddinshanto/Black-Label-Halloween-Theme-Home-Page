<?php
if (!defined('ABSPATH')) exit;

/**
 * Plugin Activation Handler
 * Creates custom database tables: {wpdb->prefix}spin_win_campaigns & {wpdb->prefix}spin_win_logs
 */
class Spin_Win_Activator {
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table 1: Campaigns
        $table_campaigns = $wpdb->prefix . 'spin_win_campaigns';
        $sql_campaigns = "CREATE TABLE IF NOT EXISTS $table_campaigns (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            status VARCHAR(20) DEFAULT 'active' NOT NULL,
            slices LONGTEXT NOT NULL,
            display_rules LONGTEXT NOT NULL,
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql_campaigns);

        // Table 2: Logs
        $table_logs = $wpdb->prefix . 'spin_win_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT(20) NOT NULL DEFAULT 1,
            user_id BIGINT(20) DEFAULT 0 NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(30) DEFAULT '',
            slice_index INT(11) NOT NULL,
            prize_type VARCHAR(50) NOT NULL,
            prize_value VARCHAR(255) NOT NULL,
            coupon_code VARCHAR(100) DEFAULT '',
            is_redeemed TINYINT(1) DEFAULT 0 NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY email_idx (email),
            KEY ip_idx (ip_address)
        ) $charset_collate;";
        dbDelta($sql_logs);

        // Set default campaign if none exists
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_campaigns");
        if ((int)$count === 0) {
            self::seed_default_campaign();
        }

        // Default options
        if (!get_option('spin_win_settings')) {
            $privacy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
            update_option('spin_win_settings', array(
                'honeypot_enabled'           => 1,
                'block_disposable_emails'   => 1,
                'builtin_captcha'           => 1,
                'server_side_rng'           => 1,
                'limit_spins_per_ip'        => 1,
                'email_otp_verification'    => 0,
                'ip_limit_hours'            => 24,
                'cookie_limit_days'         => 7,
                'consent_checkbox_required' => 1,
                'consent_text'              => __('I agree to the Terms & Privacy Policy.', 'spin-win-woo'),
                'privacy_policy_url'        => $privacy_url,
                'clean_on_uninstall'        => 0,
            ));
        }

        // Add endpoint
        add_rewrite_endpoint('winning-coupons', EP_ROOT | EP_PAGES);
        flush_rewrite_rules();
    }

    private static function seed_default_campaign() {
        global $wpdb;
        $table_campaigns = $wpdb->prefix . 'spin_win_campaigns';

        $default_slices = array(
            array('label' => '10% OFF CART', 'prizeType' => 'percentage_discount', 'prizeValue' => '10', 'couponCodePrefix' => 'SAVE10', 'probability' => 25, 'stockLimit' => -1, 'stockClaimed' => 0, 'bgColor' => '#4f46e5', 'textColor' => '#ffffff'),
            array('label' => '$5 OFF CART', 'prizeType' => 'fixed_cart_discount', 'prizeValue' => '5', 'couponCodePrefix' => 'FIVE', 'probability' => 20, 'stockLimit' => -1, 'stockClaimed' => 0, 'bgColor' => '#06b6d4', 'textColor' => '#ffffff'),
            array('label' => 'FREE SHIPPING', 'prizeType' => 'free_shipping', 'prizeValue' => '0', 'couponCodePrefix' => 'SHIPFREE', 'probability' => 15, 'stockLimit' => -1, 'stockClaimed' => 0, 'bgColor' => '#10b981', 'textColor' => '#ffffff'),
            array('label' => '20% MEGA DEAL', 'prizeType' => 'percentage_discount', 'prizeValue' => '20', 'couponCodePrefix' => 'MEGA20', 'probability' => 10, 'stockLimit' => 50, 'stockClaimed' => 0, 'bgColor' => '#f59e0b', 'textColor' => '#ffffff'),
            array('label' => 'FREE GIFT', 'prizeType' => 'free_gift_product', 'prizeValue' => '0', 'couponCodePrefix' => 'FREEGIFT', 'probability' => 5, 'stockLimit' => 20, 'stockClaimed' => 0, 'bgColor' => '#ec4899', 'textColor' => '#ffffff'),
            array('label' => '$15 OFF HOODIE', 'prizeType' => 'fixed_product_discount', 'prizeValue' => '15', 'couponCodePrefix' => 'HOODIE15', 'probability' => 10, 'stockLimit' => -1, 'stockClaimed' => 0, 'bgColor' => '#8b5cf6', 'textColor' => '#ffffff'),
            array('label' => 'EXTRA SPIN', 'prizeType' => 'try_again', 'prizeValue' => '1', 'couponCodePrefix' => 'RETRY', 'probability' => 10, 'stockLimit' => -1, 'stockClaimed' => 0, 'bgColor' => '#64748b', 'textColor' => '#ffffff'),
            array('label' => 'NO LUCK TODAY', 'prizeType' => 'none', 'prizeValue' => '0', 'couponCodePrefix' => 'NONE', 'probability' => 5, 'stockLimit' => -1, 'stockClaimed' => 0, 'bgColor' => '#94a3b8', 'textColor' => '#ffffff'),
        );

        $default_rules = array(
            'pageTargeting'   => 'all',
            'deviceTargeting' => 'all',
            'userRestriction' => 'all',
            'cartThreshold'   => 0,
            'triggerType'     => 'time_delay',
            'delaySeconds'    => 3,
            'frequencyDays'   => 7,
        );

        $wpdb->insert($table_campaigns, array(
            'title'         => 'Spring Festive Lucky Wheel',
            'status'        => 'active',
            'slices'        => wp_json_encode($default_slices),
            'display_rules' => wp_json_encode($default_rules),
            'created_at'    => current_time('mysql'),
        ));
    }
}
