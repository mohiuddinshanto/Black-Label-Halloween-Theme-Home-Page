<?php
if (!defined('ABSPATH')) exit;

/**
 * REST API Controller for Spin & Win
 */
class Spin_Win_Rest_Api {
    private $namespace = 'spin-win/v1';

    private function spin_timestamp($mysql_datetime) {
        try {
            $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            return (new DateTimeImmutable($mysql_datetime, $timezone))->getTimestamp();
        } catch (Exception $e) {
            return time();
        }
    }

    private function ip_lock_expiry($mysql_datetime) {
        $settings = get_option('spin_win_settings', array());
        $limit_enabled = !array_key_exists('limit_spins_per_ip', $settings) || !empty($settings['limit_spins_per_ip']);
        if (!$limit_enabled) return null;
        $hours = max(1, (int)($settings['ip_limit_hours'] ?? 24));
        return $this->spin_timestamp($mysql_datetime) + ($hours * HOUR_IN_SECONDS);
    }

    private function restore_previous_spin($spin) {
        global $wpdb;

        if (!empty($spin->coupon_code)) {
            // Older Spin & Win coupons had a 48-hour expiry. Keep an earned
            // reward available when the original visitor returns later.
            if (class_exists('WC_Coupon')) {
                $coupon = new WC_Coupon($spin->coupon_code);
                if ($coupon->get_id() && $coupon->get_date_expires()) {
                    $coupon->set_date_expires(null);
                    $coupon->save();
                }
            }

            if (function_exists('WC') && WC()->session) {
                WC()->session->set('spin_win_coupon', $spin->coupon_code);
                WC()->session->set('spin_win_email', $spin->email);
                do_action('spin_win_coupon_won');
            }
        }

        $label = $spin->prize_value ?: __('Your saved reward', 'spin-win-woo');
        $campaign_table = $wpdb->prefix . 'spin_win_campaigns';
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT slices FROM $campaign_table WHERE id = %d",
            $spin->campaign_id
        ));
        $slices = $campaign ? json_decode($campaign->slices, true) : array();
        if (isset($slices[$spin->slice_index]['label'])) {
            $label = $slices[$spin->slice_index]['label'];
        }

        return rest_ensure_response(array(
            'success'      => true,
            'already_spun' => true,
            'slice_index'  => (int)$spin->slice_index,
            'label'        => $label,
            'prize_type'   => $spin->prize_type,
            'prize_value'  => $spin->prize_value,
            'coupon_code'  => $spin->coupon_code,
            'log_id'       => (int)$spin->id,
            'email'        => $spin->email,
            'spin_created_at' => $this->spin_timestamp($spin->created_at),
            'ip_lock_expires_at' => $this->ip_lock_expiry($spin->created_at),
        ));
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/spin', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_spin_request'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route($this->namespace, '/apply-coupon', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_apply_coupon'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route($this->namespace, '/restore-reward', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_restore_reward'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route($this->namespace, '/lookup-reward', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_lookup_reward'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route($this->namespace, '/verify-otp', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_verify_otp'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route($this->namespace, '/captcha', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_fresh_captcha'),
            'permission_callback' => '__return_true',
        ));
    }

    public function get_fresh_captcha() {
        return rest_ensure_response(Spin_Win_Security::generate_math_captcha());
    }

    public function handle_apply_coupon($request) {
        $params = $request->get_json_params();
        $nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? $_SERVER['HTTP_X_WP_NONCE'] : ($params['nonce'] ?? '');
        if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('invalid_nonce', __('Security token expired. Please refresh the page.', 'spin-win-woo'), array('status' => 403));
        }

        if (!function_exists('WC') || !WC()->session || !WC()->cart) {
            return new WP_Error('cart_unavailable', __('Your cart is not available right now.', 'spin-win-woo'), array('status' => 503));
        }

        $coupon_code = wc_format_coupon_code(sanitize_text_field($params['coupon_code'] ?? ''));
        if (!$coupon_code) {
            return new WP_Error('missing_coupon', __('No reward coupon was provided.', 'spin-win-woo'), array('status' => 400));
        }

        if (WC()->cart->has_discount($coupon_code)) {
            return rest_ensure_response(array('success' => true, 'applied' => true));
        }

        $session_coupon = wc_format_coupon_code(WC()->session->get('spin_win_coupon', ''));
        if (!$session_coupon || !hash_equals($session_coupon, $coupon_code)) {
            return new WP_Error('coupon_session_mismatch', __('This reward does not belong to the current cart session.', 'spin-win-woo'), array('status' => 403));
        }

        if (WC()->cart->is_empty()) {
            return rest_ensure_response(array('success' => true, 'applied' => false, 'reason' => 'empty_cart'));
        }

        $email = WC()->session->get('spin_win_email');
        if (WC()->customer && $email && is_callable(array(WC()->customer, 'set_billing_email'))) {
            WC()->customer->set_billing_email($email);
        }

        $applied = WC()->cart->apply_coupon($coupon_code);
        if (!$applied && !WC()->cart->has_discount($coupon_code)) {
            return rest_ensure_response(array('success' => true, 'applied' => false, 'reason' => 'coupon_rejected'));
        }

        WC()->session->__unset('spin_win_coupon');
        WC()->session->__unset('spin_win_email');

        return rest_ensure_response(array('success' => true, 'applied' => true));
    }

    public function handle_restore_reward($request) {
        $params = $request->get_json_params();
        $nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? $_SERVER['HTTP_X_WP_NONCE'] : ($params['nonce'] ?? '');
        if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('invalid_nonce', __('Security token expired. Please refresh the page.', 'spin-win-woo'), array('status' => 403));
        }

        $log_id = absint($params['log_id'] ?? 0);
        $email = sanitize_email($params['email'] ?? '');
        $coupon_code = wc_format_coupon_code(sanitize_text_field($params['coupon_code'] ?? ''));
        if (!$log_id || !is_email($email) || !$coupon_code) {
            return new WP_Error('invalid_reward', __('The saved reward could not be verified.', 'spin-win-woo'), array('status' => 400));
        }

        global $wpdb;
        $table_logs = $wpdb->prefix . 'spin_win_logs';
        $spin = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_logs WHERE id = %d AND email = %s AND coupon_code = %s LIMIT 1",
            $log_id,
            $email,
            $coupon_code
        ));
        if (!$spin) {
            return new WP_Error('reward_not_found', __('The saved reward could not be verified.', 'spin-win-woo'), array('status' => 403));
        }

        return $this->restore_previous_spin($spin);
    }

    public function handle_lookup_reward($request) {
        $params = $request->get_json_params();
        $nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? $_SERVER['HTTP_X_WP_NONCE'] : ($params['nonce'] ?? '');
        if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('invalid_nonce', __('Security token expired. Please refresh the page.', 'spin-win-woo'), array('status' => 403));
        }

        $settings = get_option('spin_win_settings', array());
        $limit_enabled = !array_key_exists('limit_spins_per_ip', $settings) || !empty($settings['limit_spins_per_ip']);
        if (!$limit_enabled) return rest_ensure_response(array('success' => true, 'available' => false));

        $hours = max(1, (int)($settings['ip_limit_hours'] ?? 24));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $spin = Spin_Win_Security::get_previous_spin_for_ip($ip, $hours);
        if (!$spin) return rest_ensure_response(array('success' => true, 'available' => false));

        return $this->restore_previous_spin($spin);
    }

    public function handle_spin_request($request) {
        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_body_params();
        }

        // Nonce check
        $nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? $_SERVER['HTTP_X_WP_NONCE'] : ($params['nonce'] ?? '');
        if (!empty($nonce) && !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('invalid_nonce', __('Security token expired. Please refresh the page.', 'spin-win-woo'), array('status' => 403));
        }

        // Honeypot check
        if (!empty($params['spin_win_hp'])) {
            return new WP_Error('spam_detected', __('Bot activity detected.', 'spin-win-woo'), array('status' => 400));
        }

        $email = sanitize_email($params['email'] ?? '');
        $name  = sanitize_text_field($params['name'] ?? '');
        $phone = sanitize_text_field($params['phone'] ?? '');

        if (!is_email($email)) {
            return new WP_Error('invalid_email', __('Please provide a valid email address.', 'spin-win-woo'), array('status' => 400));
        }

        if (Spin_Win_Security::is_disposable_email($email)) {
            return new WP_Error('disposable_blocked', __('Temporary or disposable emails are not permitted.', 'spin-win-woo'), array('status' => 400));
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $settings = get_option('spin_win_settings', array());
        $ip_limit_enabled = !array_key_exists('limit_spins_per_ip', $settings) || !empty($settings['limit_spins_per_ip']);
        $ip_limit_hours = max(1, (int)($settings['ip_limit_hours'] ?? 24));
        $previous_spin = $ip_limit_enabled ? Spin_Win_Security::get_previous_spin_for_ip($ip, $ip_limit_hours) : false;
        if ($previous_spin) {
            return $this->restore_previous_spin($previous_spin);
        }

        $limit_check = Spin_Win_Security::check_rate_limit($email, $ip);
        if (is_wp_error($limit_check)) {
            return $limit_check;
        }

        // Captcha
        $settings = get_option('spin_win_settings', array());
        if (!empty($settings['builtin_captcha']) && !empty($params['captcha_answer'])) {
            if (!Spin_Win_Security::verify_math_captcha($params['captcha_answer'], $params['captcha_hash'] ?? '')) {
                return new WP_Error('invalid_captcha', __('Incorrect math CAPTCHA answer. Please try again.', 'spin-win-woo'), array('status' => 400));
            }
        }

        global $wpdb;
        $table_campaigns = $wpdb->prefix . 'spin_win_campaigns';
        $campaign = $wpdb->get_row("SELECT * FROM $table_campaigns WHERE status = 'active' ORDER BY id DESC LIMIT 1");
        if (!$campaign) {
            return new WP_Error('no_campaign', __('No active campaign found.', 'spin-win-woo'), array('status' => 404));
        }

        $slices = json_decode($campaign->slices, true);
        if (empty($slices)) {
            return new WP_Error('no_slices', __('Campaign slices not configured.', 'spin-win-woo'), array('status' => 500));
        }

        // Serialize spins from the same IP so two simultaneous requests cannot
        // both pass the one-spin check before either reward is logged.
        $spin_lock = Spin_Win_Security::acquire_spin_lock($ip);
        if (!$spin_lock) {
            return new WP_Error('spin_lock_busy', __('Your spin is being processed. Please try again shortly.', 'spin-win-woo'), array('status' => 503));
        }
        $previous_spin = $ip_limit_enabled ? Spin_Win_Security::get_previous_spin_for_ip($ip, $ip_limit_hours) : false;
        if ($previous_spin) {
            Spin_Win_Security::release_spin_lock($spin_lock);
            return $this->restore_previous_spin($previous_spin);
        }

        do_action('spin_win_before_spin', $campaign->id, array('email' => $email, 'name' => $name));

        $rules = json_decode($campaign->display_rules ?? '[]', true) ?: array();
        $outcome_settings = isset($rules['outcome']) ? $rules['outcome'] : array('mode' => 'weighted_random');

        $slice_index = Spin_Win_Security::calculate_server_result($slices, $outcome_settings);
        $won_slice = $slices[$slice_index];

        $prize_type = $won_slice['prizeType'] ?? 'none';
        $prize_value = $won_slice['prizeValue'] ?? '';
        $prefix = $won_slice['couponCodePrefix'] ?? 'SPIN';

        $coupon_code = '';
        if ($prize_type !== 'none' && $prize_type !== 'try_again') {
            $coupon_data = Spin_Win_WooCommerce_Integration::create_wc_coupon($prize_type, $prize_value, $prefix, $email);
            if ($coupon_data) {
                $coupon_code = $coupon_data['code'];
                if (function_exists('WC') && WC()->session) {
                    WC()->session->set('spin_win_coupon', $coupon_code);
                    WC()->session->set('spin_win_email', $email);
                    // Apply now if the visitor already has a populated cart.
                    // Otherwise the WooCommerce cart hooks retry later.
                    do_action('spin_win_coupon_won');
                }
            }
        }

        if (isset($slices[$slice_index]['stockClaimed'])) {
            $slices[$slice_index]['stockClaimed']++;
            $wpdb->update($table_campaigns, array('slices' => wp_json_encode($slices)), array('id' => $campaign->id));
        }

        $table_logs = $wpdb->prefix . 'spin_win_logs';
        $spin_created_at = current_time('mysql');
        $log_inserted = $wpdb->insert($table_logs, array(
            'campaign_id' => $campaign->id,
            'user_id'     => get_current_user_id(),
            'name'        => $name,
            'email'       => $email,
            'phone'       => $phone,
            'slice_index' => $slice_index,
            'prize_type'  => $prize_type,
            'prize_value' => $prize_value,
            'coupon_code' => $coupon_code,
            'is_redeemed' => 0,
            'ip_address'  => $ip,
            'user_agent'  => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'created_at'  => $spin_created_at,
        ));
        if (!$log_inserted) {
            Spin_Win_Security::release_spin_lock($spin_lock);
            return new WP_Error('spin_log_failed', __('We could not save your reward. Please contact the store.', 'spin-win-woo'), array('status' => 500));
        }
        $log_id = $wpdb->insert_id;
        Spin_Win_Security::release_spin_lock($spin_lock);

        if (!empty($coupon_code)) {
            Spin_Win_Email::send_coupon_email($email, $name, $coupon_code, $won_slice['label']);
        }

        do_action('spin_win_after_win', $log_id, $won_slice);

        return rest_ensure_response(array(
            'success'     => true,
            'slice_index' => $slice_index,
            'label'       => $won_slice['label'],
            'prize_type'  => $prize_type,
            'prize_value' => $prize_value,
            'coupon_code' => $coupon_code,
            'log_id'      => $log_id,
            'spin_created_at' => $this->spin_timestamp($spin_created_at),
            'ip_lock_expires_at' => $this->ip_lock_expiry($spin_created_at),
        ));
    }

    public function handle_verify_otp($request) {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email'] ?? '');
        $otp   = sanitize_text_field($params['otp'] ?? '');

        if (!Spin_Win_Security::verify_otp($email, $otp)) {
            return new WP_Error('invalid_otp', __('Invalid or expired 6-digit verification code.', 'spin-win-woo'), array('status' => 400));
        }

        return rest_ensure_response(array('success' => true, 'verified' => true));
    }
}
