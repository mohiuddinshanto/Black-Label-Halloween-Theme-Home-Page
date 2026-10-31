<?php
if (!defined('ABSPATH')) exit;

/**
 * Fired during plugin deactivation.
 */
class Spin_Win_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook('spin_win_daily_cleanup_cron');
        flush_rewrite_rules();
    }
}
