<?php
if (!defined('ABSPATH')) exit;

/**
 * Deep WooCommerce Integration:
 * - Dynamic Coupon Creation (WC_Coupon)
 * - Auto-Apply in Cart/Checkout Session
 * - Free Gift Product Auto-Add
 * - "My Winning Coupons" Tab in Customer My Account
 */
class Spin_Win_WooCommerce_Integration {

    public function init_sessions_and_endpoints() {
        if (function_exists('WC') && WC()->session && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }

        add_rewrite_endpoint('winning-coupons', EP_ROOT | EP_PAGES);

        // Apply rewards immediately when a spin completes, and retry when the
        // customer returns to a populated cart or checkout (including Blocks).
        add_action('spin_win_coupon_won', array($this, 'apply_session_coupon'));
        add_action('woocommerce_cart_loaded_from_session', array($this, 'apply_session_coupon'), 20);
        add_action('woocommerce_add_to_cart', array($this, 'apply_session_coupon'), 20);
        add_action('woocommerce_before_cart', array($this, 'apply_session_coupon'));
        add_action('woocommerce_before_checkout_form', array($this, 'apply_session_coupon'));
    }

    public function apply_session_coupon() {
        if (!function_exists('WC') || !WC()->session || !WC()->cart) return;

        $coupon_code = WC()->session->get('spin_win_coupon');
        if (!$coupon_code) return;

        if (WC()->cart->has_discount($coupon_code)) {
            WC()->session->__unset('spin_win_coupon');
            WC()->session->__unset('spin_win_email');
            return;
        }

        // Empty carts cannot accept discounts. Keep the reward in session and
        // retry after an item is added or when the cart/checkout is loaded.
        if (WC()->cart->is_empty()) return;

        // The coupon is email restricted. Keep the wheel email on Woo's
        // in-memory customer so coupon validation and cart totals use it.
        $customer = WC()->customer;
        $spin_email = WC()->session->get('spin_win_email');
        if ($customer && $spin_email && is_callable(array($customer, 'set_billing_email'))) {
            $customer->set_billing_email($spin_email);
        }

        $applied = WC()->cart->apply_coupon($coupon_code);

        if ($applied) {
            WC()->session->__unset('spin_win_coupon');
            WC()->session->__unset('spin_win_email');
        }
    }

    public static function create_wc_coupon($prize_type, $prize_value, $code_prefix = 'SPIN', $email = '') {
        if (!class_exists('WC_Coupon')) return false;

        $random_suffix = strtoupper(wp_generate_password(8, false));
        $coupon_code = sanitize_text_field($code_prefix . '-' . $random_suffix);

        $coupon = new WC_Coupon();
        $coupon->set_code($coupon_code);
        $coupon->set_description(__('Reward won via Spin & Win Lucky Wheel', 'spin-win-woo'));
        $coupon->set_usage_limit(1);
        $coupon->set_usage_limit_per_user(1);
        $coupon->set_individual_use(true);

        if (!empty($email)) {
            $coupon->set_email_restrictions(array($email));
        }

        switch ($prize_type) {
            case 'percentage_discount':
                $coupon->set_discount_type('percent');
                $coupon->set_amount((float)$prize_value);
                break;
            case 'fixed_cart_discount':
                $coupon->set_discount_type('fixed_cart');
                $coupon->set_amount((float)$prize_value);
                break;
            case 'fixed_product_discount':
                $coupon->set_discount_type('fixed_product');
                $coupon->set_amount((float)$prize_value);
                break;
            case 'free_shipping':
                $coupon->set_discount_type('fixed_cart');
                $coupon->set_amount(0);
                $coupon->set_free_shipping(true);
                break;
            default:
                $coupon->set_discount_type('fixed_cart');
                $coupon->set_amount(0);
                break;
        }

        $coupon->save();

        return array(
            'code'    => $coupon_code,
            'id'      => $coupon->get_id(),
            'expires' => null,
        );
    }

    public function add_my_account_tab($items) {
        $new_items = array();
        foreach ($items as $key => $title) {
            $new_items[$key] = $title;
            if ($key === 'orders') {
                $new_items['winning-coupons'] = __('🎡 My Winning Coupons', 'spin-win-woo');
            }
        }
        return $new_items;
    }

    public function render_my_account_coupons() {
        if (!is_user_logged_in()) {
            echo '<p>' . esc_html__('Please log in to view your coupons.', 'spin-win-woo') . '</p>';
            return;
        }

        global $wpdb;
        $current_user = wp_get_current_user();
        $table_logs = $wpdb->prefix . 'spin_win_logs';

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_logs WHERE email = %s AND coupon_code != '' ORDER BY created_at DESC",
            $current_user->user_email
        ));

        echo '<h3>' . esc_html__('Your Lucky Wheel Rewards', 'spin-win-woo') . '</h3>';

        if (empty($logs)) {
            echo '<p>' . esc_html__('You haven’t spun the wheel yet or have no active coupons.', 'spin-win-woo') . '</p>';
            return;
        }

        echo '<table class="woocommerce-orders-table shop_table shop_table_responsive">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Coupon Code', 'spin-win-woo') . '</th>';
        echo '<th>' . esc_html__('Prize', 'spin-win-woo') . '</th>';
        echo '<th>' . esc_html__('Status', 'spin-win-woo') . '</th>';
        echo '<th>' . esc_html__('Date Won', 'spin-win-woo') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($logs as $log) {
            $status_label = $log->is_redeemed 
                ? '<span style="color:#10b981;font-weight:bold;">' . esc_html__('Redeemed', 'spin-win-woo') . '</span>'
                : '<span style="color:#f59e0b;font-weight:bold;">' . esc_html__('Available', 'spin-win-woo') . '</span>';

            echo '<tr>';
            echo '<td><code>' . esc_html($log->coupon_code) . '</code></td>';
            echo '<td>' . esc_html($log->prize_value) . ' (' . esc_html($log->prize_type) . ')</td>';
            echo '<td>' . $status_label . '</td>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($log->created_at))) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
