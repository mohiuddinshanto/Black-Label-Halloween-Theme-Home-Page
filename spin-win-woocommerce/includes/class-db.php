<?php
if (!defined('ABSPATH')) exit;

/**
 * Database abstraction layer for Spin & Win
 */
class Spin_Win_DB {
    public static function get_active_campaign() {
        global $wpdb;
        $table = $wpdb->prefix . 'spin_win_campaigns';
        return $wpdb->get_row("SELECT * FROM $table WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    }

    public static function insert_log($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'spin_win_logs';
        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    public static function get_logs($limit = 50, $offset = 0, $search = '', $is_redeemed = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'spin_win_logs';
        $query = "SELECT * FROM $table WHERE 1=1";
        $params = array();

        if (!empty($search)) {
            $query .= " AND (name LIKE %s OR email LIKE %s OR coupon_code LIKE %s)";
            $wildcard = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $wildcard;
            $params[] = $wildcard;
            $params[] = $wildcard;
        }

        if ($is_redeemed !== null && $is_redeemed !== '') {
            $query .= " AND is_redeemed = %d";
            $params[] = (int)$is_redeemed;
        }

        $query .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($query, ...$params));
    }
}
