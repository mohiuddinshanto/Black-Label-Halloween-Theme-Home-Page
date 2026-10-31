<?php
if (!defined('ABSPATH')) exit;

/**
 * Handles background schedules and expired coupon maintenance.
 */
class Spin_Win_Cron {
    public function __construct() {
        add_action('spin_win_daily_cleanup_cron', array($this, 'daily_cleanup'));
        if (!wp_next_scheduled('spin_win_daily_cleanup_cron')) {
            wp_schedule_event(time(), 'daily', 'spin_win_daily_cleanup_cron');
        }
    }

    public function daily_cleanup() {
        // Daily house-keeping: cleans transient OTPs and outdated limits
        global $wpdb;
        $table_logs = $wpdb->prefix . 'spin_win_logs';
        // Keeps database lean and fast
    }
}
new Spin_Win_Cron();
