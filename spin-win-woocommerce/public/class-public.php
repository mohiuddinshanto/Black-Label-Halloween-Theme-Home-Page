<?php
if (!defined('ABSPATH')) exit;

/**
 * Public facing assets, floating launcher, and modal popup engine
 */
class Spin_Win_Public {
    private static $already_rendered = false;

    public function enqueue_assets() {
        global $wpdb;
        $table = $wpdb->prefix . 'spin_win_campaigns';
        $campaign = $wpdb->get_row("SELECT * FROM $table WHERE status = 'active' ORDER BY id ASC LIMIT 1");
        if (!$campaign) {
            $campaign = $wpdb->get_row("SELECT * FROM $table ORDER BY id ASC LIMIT 1");
        }

        $slices = ($campaign && !empty($campaign->slices)) ? json_decode($campaign->slices, true) : array();
        $rules = ($campaign && !empty($campaign->display_rules)) ? json_decode($campaign->display_rules, true) : array();

        // Change asset URLs when campaign appearance/settings change so browsers
        // and page optimizers fetch the current launcher configuration bundle.
        $campaign_asset_version = $campaign
            ? substr(md5(($campaign->display_rules ?? '') . '|' . ($campaign->slices ?? '')), 0, 12)
            : 'default';

        if (empty($slices)) {
            $slices = array(
                array('id' => 'slice-1', 'label' => '10% OFF CART', 'prizeType' => 'percentage_discount', 'prizeValue' => '10', 'couponCodePrefix' => 'SAVE10', 'probability' => 25, 'bgColor' => '#4f46e5', 'textColor' => '#ffffff'),
                array('id' => 'slice-2', 'label' => '$5 OFF CART', 'prizeType' => 'fixed_cart_discount', 'prizeValue' => '5', 'couponCodePrefix' => 'FIVE', 'probability' => 20, 'bgColor' => '#06b6d4', 'textColor' => '#ffffff'),
                array('id' => 'slice-3', 'label' => 'FREE SHIPPING', 'prizeType' => 'free_shipping', 'prizeValue' => '0', 'couponCodePrefix' => 'SHIPFREE', 'probability' => 15, 'bgColor' => '#10b981', 'textColor' => '#ffffff'),
                array('id' => 'slice-4', 'label' => '20% MEGA DEAL', 'prizeType' => 'percentage_discount', 'prizeValue' => '20', 'couponCodePrefix' => 'MEGA20', 'probability' => 10, 'bgColor' => '#f59e0b', 'textColor' => '#ffffff'),
                array('id' => 'slice-5', 'label' => 'FREE GIFT', 'prizeType' => 'free_gift_product', 'prizeValue' => '0', 'couponCodePrefix' => 'FREEGIFT', 'probability' => 5, 'bgColor' => '#ec4899', 'textColor' => '#ffffff'),
                array('id' => 'slice-6', 'label' => 'EXTRA SPIN', 'prizeType' => 'try_again', 'prizeValue' => '1', 'couponCodePrefix' => 'RETRY', 'probability' => 15, 'bgColor' => '#64748b', 'textColor' => '#ffffff'),
                array('id' => 'slice-7', 'label' => 'NO LUCK TODAY', 'prizeType' => 'none', 'prizeValue' => '0', 'couponCodePrefix' => 'NONE', 'probability' => 10, 'bgColor' => '#94a3b8', 'textColor' => '#ffffff'),
            );
        }

        wp_enqueue_style(
            'spin-win-css',
            SPIN_WIN_PLUGIN_URL . 'assets/css/spin-wheel.css',
            array(),
            filemtime(SPIN_WIN_PLUGIN_DIR . 'assets/css/spin-wheel.css') . '-' . $campaign_asset_version
        );

        wp_enqueue_script(
            'spin-win-js',
            SPIN_WIN_PLUGIN_URL . 'assets/js/spin-wheel.js',
            array(),
            filemtime(SPIN_WIN_PLUGIN_DIR . 'assets/js/spin-wheel.js') . '-' . $campaign_asset_version,
            true
        );

        $cart_url = !empty($rules['cart_button_url'])
            ? esc_url_raw($rules['cart_button_url'])
            : (function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url());
        $spin_settings = get_option('spin_win_settings', array());

        wp_localize_script('spin-win-js', 'spinWinData', array(
            'apiUrl'     => esc_url_raw(rest_url('spin-win/v1/spin')),
            'applyCouponUrl' => esc_url_raw(rest_url('spin-win/v1/apply-coupon')),
            'nonce'      => wp_create_nonce('wp_rest'),
            'slices'     => $slices,
            'rules'      => $rules,
            'cartUrl'    => esc_url($cart_url),
            'restoreRewardUrl' => esc_url_raw(rest_url('spin-win/v1/restore-reward')),
            'lookupRewardUrl' => esc_url_raw(rest_url('spin-win/v1/lookup-reward')),
            'ipLimitEnabled' => !array_key_exists('limit_spins_per_ip', $spin_settings) || !empty($spin_settings['limit_spins_per_ip']),
            'ipLimitHours' => max(1, (int)($spin_settings['ip_limit_hours'] ?? 24)),
        ));
    }

    public function render_popup_wheel() {
        if (self::$already_rendered) return;
        self::$already_rendered = true;

        global $wpdb;
        $table = $wpdb->prefix . 'spin_win_campaigns';
        $campaign = $wpdb->get_row("SELECT * FROM $table WHERE status = 'active' ORDER BY id ASC LIMIT 1");
        if (!$campaign) {
            $campaign = $wpdb->get_row("SELECT * FROM $table ORDER BY id ASC LIMIT 1");
        }

        $rules = ($campaign && !empty($campaign->display_rules)) ? json_decode($campaign->display_rules, true) : array();
        $cart_url = !empty($rules['cart_button_url'])
            ? esc_url($rules['cart_button_url'])
            : (function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url());
        $headline = !empty($rules['headline_text']) ? $rules['headline_text'] : __('Spin the wheel, win up to $120 off', 'spin-win-woo');
        if ($headline === 'Spin the Wheel & Win Up to 20% Off!') {
            $headline = 'Spin the wheel, win up to $120 off';
        }
        $subheadline = !empty($rules['subheadline_text']) ? $rules['subheadline_text'] : __('Enter your details below to unlock instant discounts and gifts on your order.', 'spin-win-woo');
        ?>

        <!-- The widget is moved into the explicit inline mount by the frontend script. -->
        <!-- 2. INLINE LUCKY WHEEL -->
        <div id="spin-win-modal-overlay" class="spin-win-overlay" style="display:none;">
            <div class="spin-win-modal" role="dialog" aria-modal="true">
                <button type="button" id="spin-win-close-btn" class="spin-win-close-btn" aria-label="Close modal">&times;</button>
                
                <!-- Left: Canvas Wheel Column -->
                <div class="spin-win-wheel-column">
                    <div class="spin-win-pointer">
                        <svg width="34" height="42" viewBox="0 0 34 42" fill="none">
                            <path d="M17 40L4 8C3 6 4.5 3 7 3H27C29.5 3 31 6 30 8L17 40Z" fill="#fbbf24" stroke="#ffffff" stroke-width="2"/>
                            <circle cx="17" cy="11" r="4" fill="#0f172a" />
                        </svg>
                    </div>
                    <canvas id="spin-win-canvas" width="360" height="360"></canvas>
                </div>

                <!-- Right: Form & Results Column -->
                <div class="spin-win-form-column">
                    <!-- Form State -->
                    <div id="spin-win-view-form">
                        <div class="spin-win-tag-badge">🎁 <?php esc_html_e('Shopper Reward', 'spin-win-woo'); ?></div>
                        <h2 class="spin-win-title"><?php echo esc_html($headline); ?></h2>
                        <p class="spin-win-desc"><?php echo esc_html($subheadline); ?></p>

                        <div id="spin-win-error" class="spin-win-alert" style="display:none;"></div>

                        <form id="spin-win-ajax-form">
                            <!-- Honeypot Bot Trap -->
                            <input type="text" name="spin_win_hp" class="spin-win-hp" style="display:none;" tabindex="-1" autocomplete="off">
                            
                            <div class="spin-win-field">
                                <label for="spin-name"><?php esc_html_e('Your Name', 'spin-win-woo'); ?></label>
                                <input type="text" id="spin-name" placeholder="<?php esc_attr_e('e.g. Alex Morgan', 'spin-win-woo'); ?>">
                            </div>

                            <div class="spin-win-field">
                                <label for="spin-email"><?php esc_html_e('Email Address', 'spin-win-woo'); ?> <span style="color:#ef4444;">*</span></label>
                                <input type="email" id="spin-email" required placeholder="<?php esc_attr_e('name@example.com', 'spin-win-woo'); ?>">
                            </div>

                            <!-- GDPR Consent Checkbox -->
                            <div class="spin-win-field spin-win-consent-row">
                                <label>
                                    <input type="checkbox" id="spin-consent" required checked>
                                    <span><?php esc_html_e('I agree to the Terms of Service & Privacy Policy.', 'spin-win-woo'); ?></span>
                                </label>
                            </div>

                            <button type="submit" id="spin-submit-btn" class="spin-win-btn-primary">
                                <span><?php esc_html_e('SPIN THE WHEEL NOW ➔', 'spin-win-woo'); ?></span>
                            </button>
                        </form>
                    </div>

                    <!-- Spinning In Progress State -->
                    <div id="spin-win-view-spinning" style="display:none;text-align:center;padding:40px 10px;">
                        <div class="spin-win-spinner"></div>
                        <h3 style="margin:16px 0 6px;font-size:18px;font-weight:700;"><?php esc_html_e('Good Luck! Spinning Wheel...', 'spin-win-woo'); ?></h3>
                        <p style="font-size:12px;color:#64748b;margin:0;"><?php esc_html_e('Server is calculating your random reward...', 'spin-win-woo'); ?></p>
                    </div>

                    <!-- Win Result State -->
                    <div id="spin-win-view-result" style="display:none;text-align:center;">
                        <div class="spin-win-trophy">🎉</div>
                        <p class="spin-win-result-intro"><?php esc_html_e('The spirits have chosen you!', 'spin-win-woo'); ?></p>
                        <span class="spin-win-win-tag"><?php esc_html_e('CONGRATULATIONS!', 'spin-win-woo'); ?></span>
                        <p class="spin-win-result-outro"><?php esc_html_e('Your Halloween treat is waiting below.', 'spin-win-woo'); ?></p>
                        <h2 id="spin-won-title" style="margin:8px 0 10px;font-size:22px;font-weight:800;color:#0f172a;"></h2>
                        <p id="spin-win-result-message" style="font-size:13px;color:#64748b;margin-bottom:18px;"><?php esc_html_e('Applying your reward to your cart…', 'spin-win-woo'); ?></p>
                        
                        <div class="spin-win-coupon-box">
                            <div>
                                <span style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#6366f1;display:block;font-weight:700;"><?php esc_html_e('Coupon Code', 'spin-win-woo'); ?></span>
                                <span id="spin-won-code" class="spin-win-coupon-code"></span>
                            </div>
                            <button type="button" id="spin-copy-code-btn" class="spin-win-btn-copy"><?php esc_html_e('Copy', 'spin-win-woo'); ?></button>
                        </div>

                        <a id="spin-won-shop-btn" href="<?php echo esc_url($cart_url); ?>" class="spin-win-btn-primary" style="text-decoration:none;display:block;margin-top:16px;">
                            <?php esc_html_e('Apply & Go ➔', 'spin-win-woo'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts(array('mode' => 'popup'), $atts, 'spin_win_wheel');
        if ($atts['mode'] === 'inline') {
            return '<div class="spin-win-inline-mount" data-spin-win-inline="1" aria-live="polite"></div>';
        }
        return '<div class="spin-win-shortcode-wrapper"><button class="spin-win-btn-primary" onclick="if(window.SpinWinApp)window.SpinWinApp.open();">' . esc_html__('🎡 Open Lucky Wheel', 'spin-win-woo') . '</button></div>';
    }
}
