<?php
if (!defined('ABSPATH')) exit;

/**
 * Global Options & Security Settings Form
 */
class Spin_Win_Admin_Settings {
    public static function render() {
        if (!current_user_can('manage_woocommerce')) return;

        if (isset($_POST['spin_win_save_settings']) && check_admin_referer('spin_win_settings_nonce')) {
            $settings = array(
                'honeypot_enabled'           => isset($_POST['honeypot_enabled']) ? 1 : 0,
                'block_disposable_emails'   => isset($_POST['block_disposable_emails']) ? 1 : 0,
                'builtin_captcha'           => isset($_POST['builtin_captcha']) ? 1 : 0,
                'server_side_rng'           => isset($_POST['server_side_rng']) ? 1 : 0,
                'limit_spins_per_ip'        => isset($_POST['limit_spins_per_ip']) ? 1 : 0,
                'email_otp_verification'    => isset($_POST['email_otp_verification']) ? 1 : 0,
                'ip_limit_hours'            => min(8760, max(1, absint($_POST['ip_limit_hours'] ?? 24))),
                'cookie_limit_days'         => absint($_POST['cookie_limit_days'] ?? 7),
                'consent_checkbox_required' => isset($_POST['consent_checkbox_required']) ? 1 : 0,
                'consent_text'              => sanitize_text_field($_POST['consent_text'] ?? ''),
                'privacy_policy_url'        => esc_url_raw($_POST['privacy_policy_url'] ?? ''),
            );
            update_option('spin_win_settings', $settings);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'spin-win-woo') . '</p></div>';
        }

        $opts = get_option('spin_win_settings', array());
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Spin & Win - Global Security & GDPR Settings', 'spin-win-woo'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('spin_win_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Server-Side RNG', 'spin-win-woo'); ?></th>
                        <td>
                            <label><input type="checkbox" name="server_side_rng" value="1" <?php checked($opts['server_side_rng'] ?? 1, 1); ?>> <?php esc_html_e('Determine winning slices strictly via server (prevent client tampering)', 'spin-win-woo'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Block Disposable Emails', 'spin-win-woo'); ?></th>
                        <td>
                            <label><input type="checkbox" name="block_disposable_emails" value="1" <?php checked($opts['block_disposable_emails'] ?? 1, 1); ?>> <?php esc_html_e('Block Mailinator, 10MinuteMail, and burner addresses', 'spin-win-woo'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Built-in Math CAPTCHA', 'spin-win-woo'); ?></th>
                        <td>
                            <label><input type="checkbox" name="builtin_captcha" value="1" <?php checked($opts['builtin_captcha'] ?? 1, 1); ?>> <?php esc_html_e('Enable lightweight arithmetic check (no external API keys needed)', 'spin-win-woo'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Honeypot Protection', 'spin-win-woo'); ?></th>
                        <td>
                            <label><input type="checkbox" name="honeypot_enabled" value="1" <?php checked($opts['honeypot_enabled'] ?? 1, 1); ?>> <?php esc_html_e('Add hidden bot trap fields', 'spin-win-woo'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Limit Spins per IP', 'spin-win-woo'); ?></th>
                        <td>
                            <label><input type="checkbox" name="limit_spins_per_ip" value="1" <?php checked($opts['limit_spins_per_ip'] ?? 1, 1); ?>> <?php esc_html_e('Restrict repeat spins from the same IP', 'spin-win-woo'); ?></label>
                            <p><label><?php esc_html_e('Show the saved reward and allow another spin after', 'spin-win-woo'); ?> <input type="number" min="1" max="8760" name="ip_limit_hours" value="<?php echo esc_attr($opts['ip_limit_hours'] ?? 24); ?>" class="small-text"> <?php esc_html_e('hours', 'spin-win-woo'); ?></label></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('GDPR Consent Checkbox', 'spin-win-woo'); ?></th>
                        <td>
                            <label><input type="checkbox" name="consent_checkbox_required" value="1" <?php checked($opts['consent_checkbox_required'] ?? 1, 1); ?>> <?php esc_html_e('Require terms acceptance before spinning', 'spin-win-woo'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Privacy Policy URL', 'spin-win-woo'); ?></th>
                        <td>
                            <input type="url" name="privacy_policy_url" value="<?php echo esc_attr($opts['privacy_policy_url'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="spin_win_save_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'spin-win-woo'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
}
