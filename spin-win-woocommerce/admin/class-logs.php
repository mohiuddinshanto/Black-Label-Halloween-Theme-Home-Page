<?php
if (!defined('ABSPATH')) exit;

/**
 * Lead Management, Spin Logs Table & CSV Export
 */
class Spin_Win_Admin_Logs {

    public static function render() {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'spin_win_logs';

        if (isset($_GET['action']) && $_GET['action'] === 'export_spin_logs' && check_admin_referer('export_spin_logs_nonce')) {
            self::export_csv();
            exit;
        }

        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $filter_redeemed = isset($_GET['is_redeemed']) ? sanitize_text_field($_GET['is_redeemed']) : '';

        $query = "SELECT * FROM $table_logs WHERE 1=1";
        $params = array();

        if (!empty($search)) {
            $query .= " AND (name LIKE %s OR email LIKE %s OR coupon_code LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if ($filter_redeemed !== '') {
            $query .= " AND is_redeemed = %d";
            $params[] = (int)$filter_redeemed;
        }

        $query .= " ORDER BY created_at DESC LIMIT 100";

        $logs = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, ...$params)) : $wpdb->get_results($query);
        $export_url = wp_nonce_url(admin_url('admin.php?page=spin-win-logs&action=export_spin_logs'), 'export_spin_logs_nonce');

        ?>
        <div class="wrap spin-win-admin-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Spin & Win Leads & Logs', 'spin-win-woo'); ?></h1>
            <a href="<?php echo esc_url($export_url); ?>" class="button button-primary" style="margin-left: 10px;">
                📥 <?php esc_html_e('Export CSV', 'spin-win-woo'); ?>
            </a>
            <hr class="wp-header-end">

            <form method="get" style="margin: 15px 0; display: flex; gap: 10px;">
                <input type="hidden" name="page" value="spin-win-logs">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search email, name or coupon...', 'spin-win-woo'); ?>" style="width: 280px;">
                <select name="is_redeemed">
                    <option value=""><?php esc_html_e('All Redemption Statuses', 'spin-win-woo'); ?></option>
                    <option value="1" <?php selected($filter_redeemed, '1'); ?>><?php esc_html_e('Redeemed in Checkout', 'spin-win-woo'); ?></option>
                    <option value="0" <?php selected($filter_redeemed, '0'); ?>><?php esc_html_e('Not Redeemed', 'spin-win-woo'); ?></option>
                </select>
                <input type="submit" class="button" value="<?php esc_attr_e('Filter Logs', 'spin-win-woo'); ?>">
            </form>

            <table class="wp-list-table widefat fixed striped posts">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Customer Name', 'spin-win-woo'); ?></th>
                        <th><?php esc_html_e('Email Address', 'spin-win-woo'); ?></th>
                        <th><?php esc_html_e('Prize Type', 'spin-win-woo'); ?></th>
                        <th><?php esc_html_e('Coupon Code', 'spin-win-woo'); ?></th>
                        <th><?php esc_html_e('Redeemed', 'spin-win-woo'); ?></th>
                        <th><?php esc_html_e('IP Address', 'spin-win-woo'); ?></th>
                        <th><?php esc_html_e('Date', 'spin-win-woo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)) : ?>
                        <tr><td colspan="7"><?php esc_html_e('No spin logs recorded yet.', 'spin-win-woo'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($logs as $log) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($log->name ?: 'Guest'); ?></strong></td>
                                <td><a href="mailto:<?php echo esc_attr($log->email); ?>"><?php echo esc_html($log->email); ?></a></td>
                                <td><?php echo esc_html(str_replace('_', ' ', strtoupper($log->prize_type))); ?></td>
                                <td><code><?php echo esc_html($log->coupon_code ?: '—'); ?></code></td>
                                <td>
                                    <?php echo $log->is_redeemed ? '<span style="color:#10b981;font-weight:bold;">✔ Yes</span>' : '<span style="color:#94a3b8;">No</span>'; ?>
                                </td>
                                <td><?php echo esc_html($log->ip_address); ?></td>
                                <td><?php echo esc_html($log->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function export_csv() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table_logs = $wpdb->prefix . 'spin_win_logs';
        $rows = $wpdb->get_results("SELECT id, name, email, phone, prize_type, prize_value, coupon_code, is_redeemed, ip_address, created_at FROM $table_logs ORDER BY created_at DESC", ARRAY_A);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=spin-win-leads-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('ID', 'Name', 'Email', 'Phone', 'Prize Type', 'Prize Value', 'Coupon Code', 'Is Redeemed', 'IP Address', 'Date Created'));

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}
