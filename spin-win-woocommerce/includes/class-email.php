<?php
if (!defined('ABSPATH')) exit;

/**
 * WooCommerce styled responsive HTML email notifications
 */
class Spin_Win_Email {

    public static function send_coupon_email($to_email, $name, $coupon_code, $prize_label) {
        $subject = sprintf(__('🎉 Your Spin & Win Reward: %s', 'spin-win-woo'), $prize_label);

        ob_start();
        ?>
        <div style="background-color:#f7f7f7;padding:30px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#333;">
            <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.06);border:1px solid #e5e7eb;">
                <div style="background:linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);padding:28px 24px;text-align:center;color:#ffffff;">
                    <h1 style="margin:0;font-size:24px;font-weight:700;">🎡 Congratulations, <?php echo esc_html($name ? $name : 'Lucky Winner'); ?>!</h1>
                    <p style="margin:8px 0 0 0;font-size:15px;opacity:0.9;">You won a reward on our Lucky Wheel</p>
                </div>
                <div style="padding:32px 28px;text-align:center;">
                    <p style="font-size:16px;line-height:1.6;color:#4b5563;margin-top:0;">Here is your exclusive coupon code to redeem during checkout:</p>
                    <div style="background:#f3f4f6;border:2px dashed #6366f1;padding:16px;border-radius:8px;display:inline-block;margin:16px 0;letter-spacing:2px;font-size:22px;font-weight:bold;color:#4338ca;">
                        <?php echo esc_html($coupon_code); ?>
                    </div>
                    <p style="font-size:14px;color:#6b7280;margin:8px 0 24px 0;">Prize: <strong><?php echo esc_html($prize_label); ?></strong> (Valid for 48 hours)</p>
                    <a href="<?php echo esc_url(function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url()); ?>" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:14px 32px;font-weight:600;font-size:15px;border-radius:8px;">
                        Apply Coupon & Shop Now &rarr;
                    </a>
                </div>
                <div style="background:#f9fafb;padding:16px;text-align:center;font-size:12px;color:#9ca3af;border-top:1px solid #f3f4f6;">
                    <?php echo esc_html(get_bloginfo('name')); ?> &bull; Powered by Spin & Win for WooCommerce
                </div>
            </div>
        </div>
        <?php
        $message = ob_get_clean();

        $headers = array('Content-Type: text/html; charset=UTF-8');
        return wp_mail($to_email, $subject, $message, $headers);
    }
}
