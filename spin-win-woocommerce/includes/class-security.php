<?php
if (!defined('ABSPATH')) exit;

/**
 * Enterprise Security & Anti-Abuse Engine
 * Server-side RNG, Nonce, Honeypot, Disposable Mail Blocker, Math Captcha & OTP
 */
class Spin_Win_Security {

    public static function get_disposable_domains() {
        $builtin = array(
            'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
            'trashmail.com', 'yopmail.com', 'sharklasers.com', 'getnada.com',
            'throwawaymail.com', 'dispostable.com', 'fakeinbox.com', 'burnermail.io',
            'crazymailing.com', 'dropmail.me', 'inboxbear.com', 'mohmal.com',
            'fakemail.net', 'mytemp.email', 'generator.email', 'trashmail.net',
            'maildrop.cc', 'nada.ltd', 'emailfake.com', 'disposablemail.com'
        );
        return apply_filters('spin_win_disposable_domains', $builtin);
    }

    public static function is_disposable_email($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return true;
        $parts = explode('@', $email);
        $domain = strtolower(trim(end($parts)));
        $list = self::get_disposable_domains();
        return in_array($domain, $list, true);
    }

    public static function calculate_server_result($slices, $outcome_settings = array()) {
        $mode = isset($outcome_settings['mode']) ? $outcome_settings['mode'] : 'weighted_random';

        // 1. FORCED SINGLE SLICE: Admin forces one exact slice to ALWAYS win every single spin
        if ($mode === 'forced_single' && !empty($outcome_settings['forced_slice_id'])) {
            $target_id = (string)$outcome_settings['forced_slice_id'];
            foreach ($slices as $index => $slice) {
                $slice_id = isset($slice['id']) ? (string)$slice['id'] : ('slice-' . ($index + 1));
                if ($slice_id === $target_id || (string)$index === $target_id) {
                    return (int)$index;
                }
            }
        }

        // 2. SELECTED POOL: Pick randomly among admin-selected allowed slices only
        if ($mode === 'selected_pool' && !empty($outcome_settings['allowed_slice_ids']) && is_array($outcome_settings['allowed_slice_ids'])) {
            $allowed = array_map('strval', $outcome_settings['allowed_slice_ids']);
            $pool = array();
            foreach ($slices as $index => $slice) {
                $slice_id = isset($slice['id']) ? (string)$slice['id'] : ('slice-' . ($index + 1));
                if (in_array($slice_id, $allowed, true) || in_array((string)$index, $allowed, true)) {
                    $pool[] = (int)$index;
                }
            }
            if (!empty($pool)) {
                $rand_key = array_rand($pool);
                return (int)$pool[$rand_key];
            }
        }

        // 3. WEIGHTED RANDOM PROBABILITY (Default)
        $weighted = array();
        foreach ($slices as $index => $slice) {
            $stock_limit = isset($slice['stockLimit']) ? (int)$slice['stockLimit'] : -1;
            $stock_claimed = isset($slice['stockClaimed']) ? (int)$slice['stockClaimed'] : 0;
            
            if ($stock_limit > 0 && $stock_claimed >= $stock_limit) {
                continue;
            }

            $prob = isset($slice['probability']) ? (float)$slice['probability'] : 0;
            if ($prob > 0) {
                $weighted[$index] = (int)($prob * 100);
            }
        }

        $weighted = apply_filters('spin_win_calculate_probability', $weighted, $slices);

        if (empty($weighted)) {
            return 0;
        }

        $total_weight = array_sum($weighted);
        $rand = mt_rand(1, max(1, $total_weight));
        $running_sum = 0;

        foreach ($weighted as $slice_index => $weight) {
            $running_sum += $weight;
            if ($rand <= $running_sum) {
                return (int)$slice_index;
            }
        }

        $keys = array_keys($weighted);
        return (int)reset($keys);
    }

    public static function check_rate_limit($email, $ip) {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'spin_win_logs';
        $settings = get_option('spin_win_settings', array());
        $ip_hours = isset($settings['ip_limit_hours']) ? (int)$settings['ip_limit_hours'] : 24;

        $limit_enabled = !array_key_exists('limit_spins_per_ip', $settings) || !empty($settings['limit_spins_per_ip']);
        if (!$limit_enabled) return true;

        $hours = max(1, $ip_hours);
        $cutoff = function_exists('wp_date')
            ? wp_date('Y-m-d H:i:s', time() - ($hours * HOUR_IN_SECONDS), wp_timezone())
            : date('Y-m-d H:i:s', current_time('timestamp') - ($hours * HOUR_IN_SECONDS));

        $ip_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE ip_address = %s AND created_at > %s",
            $ip,
            $cutoff
        ));
        if ((int)$ip_count > 0) {
            return new WP_Error('rate_limit_ip', __('This IP address has already used its one spin.', 'spin-win-woo'));
        }

        return true;
    }

    public static function get_previous_spin_for_ip($ip, $hours = 24) {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'spin_win_logs';
        $hours = max(1, (int)$hours);
        $cutoff = function_exists('wp_date')
            ? wp_date('Y-m-d H:i:s', time() - ($hours * HOUR_IN_SECONDS), wp_timezone())
            : date('Y-m-d H:i:s', current_time('timestamp') - ($hours * HOUR_IN_SECONDS));
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_logs WHERE ip_address = %s AND created_at > %s ORDER BY id DESC LIMIT 1",
            $ip,
            $cutoff
        ));
    }

    public static function acquire_spin_lock($ip) {
        global $wpdb;
        $lock_name = 'spin_win_' . md5($ip);
        $locked = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock_name));
        return ((string)$locked === '1') ? $lock_name : false;
    }

    public static function release_spin_lock($lock_name) {
        global $wpdb;
        if ($lock_name) {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }

    public static function generate_otp($email) {
        $otp = (string)wp_rand(100000, 999999);
        set_transient('spin_win_otp_' . md5($email), $otp, 10 * MINUTE_IN_SECONDS);
        return $otp;
    }

    public static function verify_otp($email, $entered_otp) {
        $stored_otp = get_transient('spin_win_otp_' . md5($email));
        if (!$stored_otp || $stored_otp !== trim($entered_otp)) {
            return false;
        }
        delete_transient('spin_win_otp_' . md5($email));
        return true;
    }

    public static function generate_math_captcha() {
        $num1 = wp_rand(1, 9);
        $num2 = wp_rand(1, 9);
        $answer = $num1 + $num2;
        $hash = wp_hash($answer . 'spin_captcha_salt');
        return array(
            'question' => sprintf(__('%d + %d = ?', 'spin-win-woo'), $num1, $num2),
            'hash'     => $hash,
        );
    }

    public static function verify_math_captcha($answer, $hash) {
        $expected_hash = wp_hash(trim($answer) . 'spin_captcha_salt');
        return hash_equals($expected_hash, $hash);
    }
}
