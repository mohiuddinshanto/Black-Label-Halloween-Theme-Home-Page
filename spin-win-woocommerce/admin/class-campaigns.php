<?php
if (!defined('ABSPATH')) exit;

/**
 * Full Interactive Admin Campaign & Slices Customizer
 */
class Spin_Win_Admin_Campaigns {
    public static function render() {
        global $wpdb;
        $table = $wpdb->prefix . 'spin_win_campaigns';
        $wheel_theme_palette = array(
            array('#ff7a00', '#0d0b0a'),
            array('#0d0b0a', '#ff7a00'),
            array('#ff7a00', '#0d0b0a'),
            array('#f6e9d7', '#0d0b0a'),
        );

        // Handle Save Action
        if (isset($_POST['spin_win_save_campaign']) && check_admin_referer('spin_win_campaign_nonce')) {
            $title = sanitize_text_field($_POST['campaign_title'] ?? 'Lucky Wheel');
            
            // Process Slices
            $raw_slices = isset($_POST['slices']) && is_array($_POST['slices']) ? $_POST['slices'] : array();
            $slices = array();

            foreach ($raw_slices as $index => $item) {
                if (empty($item['label'])) continue;
                $slices[] = array(
                    'id'               => 'slice-' . ($index + 1),
                    'label'            => sanitize_text_field($item['label']),
                    'prizeType'        => sanitize_text_field($item['prizeType'] ?? 'percentage_discount'),
                    'prizeValue'       => sanitize_text_field($item['prizeValue'] ?? '10'),
                    'couponCodePrefix' => strtoupper(sanitize_text_field($item['couponCodePrefix'] ?? 'SPIN')),
                    'probability'      => floatval($item['probability'] ?? 10),
                    'stockLimit'       => intval($item['stockLimit'] ?? -1),
                    'stockClaimed'     => intval($item['stockClaimed'] ?? 0),
                    'bgColor'          => $wheel_theme_palette[count($slices) % count($wheel_theme_palette)][0],
                    'textColor'        => $wheel_theme_palette[count($slices) % count($wheel_theme_palette)][1],
                );
            }

            // Outcome Mode & Settings
            $outcome_mode = sanitize_text_field($_POST['outcome_mode'] ?? 'weighted_random');
            $forced_slice_id = sanitize_text_field($_POST['forced_slice_id'] ?? 'slice-1');
            $allowed_slice_ids = isset($_POST['allowed_slice_ids']) && is_array($_POST['allowed_slice_ids']) 
                ? array_map('sanitize_text_field', $_POST['allowed_slice_ids']) 
                : array();

            $launcher_positions = isset($_POST['launcher_positions']) && is_array($_POST['launcher_positions'])
                ? array_values(array_intersect(array('bottom_left', 'bottom_right', 'top_left', 'top_right'), array_map('sanitize_key', $_POST['launcher_positions'])))
                : array();
            if (empty($launcher_positions)) {
                $launcher_positions = array('bottom_right');
            }

            // Process Display & Design Rules
            $display_rules = array(
                'headline_text'       => sanitize_text_field($_POST['headline_text'] ?? 'Spin the wheel, win up to $120 off'),
                'subheadline_text'    => sanitize_textarea_field($_POST['subheadline_text'] ?? 'Enter your details below to unlock instant discounts on your order.'),
                'cart_button_url'     => esc_url_raw($_POST['cart_button_url'] ?? ''),
                'floating_btn_text'   => sanitize_text_field($_POST['floating_btn_text'] ?? '🎡 Spin & Win Discount'),
                'floating_btn_pos'    => sanitize_text_field($_POST['floating_btn_pos'] ?? 'bottom_left'),
                'launcher_positions'  => $launcher_positions,
                'launcher_color'      => sanitize_hex_color($_POST['launcher_color'] ?? '#1f5b2c') ?: '#1f5b2c',
                'launcher_border'     => sanitize_hex_color($_POST['launcher_border'] ?? '#777777') ?: '#777777',
                'launcher_size'       => min(100, max(48, absint($_POST['launcher_size'] ?? 100))),
                'popup_delay'         => absint($_POST['popup_delay'] ?? 0),
                'sound_effects'       => 1,
                'confetti_enabled'    => isset($_POST['confetti_enabled']) ? 1 : 0,
                'outcome'             => array(
                    'mode'              => $outcome_mode,
                    'forced_slice_id'   => $forced_slice_id,
                    'allowed_slice_ids' => $allowed_slice_ids,
                ),
            );

            $existing = $wpdb->get_row("SELECT id FROM $table ORDER BY id ASC LIMIT 1");
            if ($existing) {
                $wpdb->update(
                    $table,
                    array(
                        'title'         => $title,
                        'slices'        => wp_json_encode($slices),
                        'display_rules' => wp_json_encode($display_rules),
                    ),
                    array('id' => $existing->id)
                );
            } else {
                $wpdb->insert(
                    $table,
                    array(
                        'title'         => $title,
                        'status'        => 'active',
                        'slices'        => wp_json_encode($slices),
                        'display_rules' => wp_json_encode($display_rules),
                        'created_at'    => current_time('mysql'),
                    )
                );
            }

            echo '<div class="notice notice-success is-dismissible" style="margin-top:15px;"><p><strong>' . esc_html__('✔ All changes saved successfully! Slices, outcome rules, and store appearance updated.', 'spin-win-woo') . '</strong></p></div>';
        }

        $campaign = $wpdb->get_row("SELECT * FROM $table ORDER BY id ASC LIMIT 1");
        $slices = json_decode($campaign->slices ?? '[]', true) ?: array();
        $rules = json_decode($campaign->display_rules ?? '[]', true) ?: array();
        $campaign_updates = array();
        if (empty($rules['headline_text']) || $rules['headline_text'] === 'Spin the Wheel & Win Up to 20% Off!') {
            $rules['headline_text'] = 'Spin the wheel, win up to $120 off';
            $campaign_updates['display_rules'] = wp_json_encode($rules);
        }
        $slices_changed = false;
        foreach ($slices as $slice_index => &$slice) {
            $theme_color = $wheel_theme_palette[$slice_index % count($wheel_theme_palette)];
            if (($slice['bgColor'] ?? '') !== $theme_color[0] || ($slice['textColor'] ?? '') !== $theme_color[1]) {
                $slice['bgColor'] = $theme_color[0];
                $slice['textColor'] = $theme_color[1];
                $slices_changed = true;
            }
        }
        unset($slice);
        if ($slices_changed) $campaign_updates['slices'] = wp_json_encode($slices);
        if ($campaign && !empty($campaign_updates)) {
            $wpdb->update($table, $campaign_updates, array('id' => $campaign->id));
        }
        $outcome = $rules['outcome'] ?? array(
            'mode'              => 'weighted_random',
            'forced_slice_id'   => 'slice-1',
            'allowed_slice_ids' => array('slice-1', 'slice-2', 'slice-3'),
        );
        ?>
        <div class="wrap spin-win-customizer-wrap" style="max-width:1100px;">
            <div style="background:#fff;padding:24px 28px;border-radius:12px;border:1px solid #ccd0d4;box-shadow:0 1px 3px rgba(0,0,0,0.04);margin-top:15px;">
                <h2 style="margin-top:0;font-size:20px;font-weight:700;">🎡 <?php esc_html_e('Customize Lucky Wheel Slices, Prizes & Outcome Rules', 'spin-win-woo'); ?></h2>
                <p style="color:#646970;font-size:13px;margin-bottom:15px;">
                    <?php esc_html_e('Easily edit labels, colors, discount values, probabilities, and force specific winning outcomes. Slices automatically update in real-time on your store front.', 'spin-win-woo'); ?>
                </p>

                <!-- Storefront Notification Banner -->
                <div style="background:#ecfdf5;border-left:4px solid #10b981;padding:12px 16px;border-radius:4px;margin-bottom:20px;font-size:13px;color:#065f46;">
                    <strong>📢 হোমস্ক্রিন ও সাইট স্ট্যাটাস:</strong> প্লাগইনটি অ্যাক্টিভ থাকা অবস্থায় স্বয়ংক্রিয়ভাবে আপনার সাইটের হোমপেজ (Homepage) এবং সকল শপ পেজে নিচে বাঁ পাশে (Bottom-Left) <strong>🎡 Spin & Win Discount</strong> বাটনটি দৃশ্যমান থাকবে। এছাড়াও যেকোনো পেজ বা ব্লকে শর্টকোড <code>[spin_win_wheel]</code> ব্যবহার করতে পারেন।
                </div>

                <section aria-labelledby="spin-win-admin-preview-title" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:28px;margin:0 0 24px;padding:22px;border:1px solid #4a2c6e;border-radius:16px;background:radial-gradient(circle at 50% 45%,#321a40,#100818 75%);color:#f6e9d7;">
                    <div style="flex:1 1 220px;max-width:330px;">
                        <h3 id="spin-win-admin-preview-title" style="margin:0 0 8px;color:#ff9a43;font-size:20px;">Trick or Treat wheel preview</h3>
                        <p style="margin:0;color:#d9cbe2;">This live preview uses your saved slice labels and the same Halloween wheel theme as the storefront.</p>
                    </div>
                    <div style="position:relative;flex:0 1 420px;width:min(100%,420px);">
                        <div style="position:absolute;z-index:2;top:-1%;left:50%;width:0;height:0;transform:translateX(-50%);border-left:18px solid transparent;border-right:18px solid transparent;border-top:38px solid #ff7a00;border-bottom:0;filter:drop-shadow(0 3px 4px #000);"></div>
                        <canvas id="spin-win-admin-wheel-preview" width="800" height="800" role="img" aria-label="Halloween prize wheel preview" style="display:block;width:100%;height:auto;filter:drop-shadow(0 0 24px #ff7a1a55);"></canvas>
                    </div>
                </section>

                <form method="post" action="" id="spin-win-admin-form">
                    <?php wp_nonce_field('spin_win_campaign_nonce'); ?>

                    <!-- SECTION 1: CAMPAIGN TITLE -->
                    <div style="margin-bottom:25px;padding-bottom:18px;border-bottom:1px solid #eee;">
                        <label style="font-weight:600;display:block;margin-bottom:6px;"><?php esc_html_e('Campaign Name', 'spin-win-woo'); ?></label>
                        <input type="text" name="campaign_title" value="<?php echo esc_attr($campaign->title ?? 'Lucky Wheel'); ?>" class="regular-text" style="font-size:14px;padding:6px 10px;width:100%;max-width:480px;">
                    </div>

                    <!-- SECTION 2: WINNING OUTCOME DETERMINATION (ফলাফল নির্ধারণ মোড) -->
                    <div style="background:#eef2ff;padding:20px 24px;border-radius:10px;border:1px solid #c7d2fe;margin-bottom:25px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <div>
                                <h3 style="margin:0;font-size:16px;color:#1e1b4b;">🎯 <?php esc_html_e('Winning Outcome Control (ফলাফল নির্ধারণ মোড)', 'spin-win-woo'); ?></h3>
                                <p style="font-size:12px;color:#4338ca;margin:4px 0 0 0;">
                                    <?php esc_html_e('যতোবারই স্পিন করুক সবসময় কি নির্দিষ্ট প্রাইজ পাবে, নাকি নির্বাচিত কয়েকটির মধ্যে ঘুরবে, নাকি স্বাভাবিক রেন্ডম? নিচে থেকে আপনার পছন্দমতো সিলেক্ট করুন:', 'spin-win-woo'); ?>
                                </p>
                            </div>
                            <span style="font-size:12px;font-weight:bold;background:#c7d2fe;color:#1e1b4b;padding:4px 10px;border-radius:12px;">
                                Active: <?php echo ($outcome['mode'] === 'forced_single') ? '🎯 Forced Specific' : (($outcome['mode'] === 'selected_pool') ? '🔀 Selected Pool' : '🎲 Random Probability'); ?>
                            </span>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:15px;">
                            <!-- Option 1: Random -->
                            <label style="background:#fff;padding:16px;border-radius:8px;border:2px solid <?php echo ($outcome['mode'] === 'weighted_random') ? '#6366f1' : '#e2e8f0'; ?>;cursor:pointer;display:block;">
                                <input type="radio" name="outcome_mode" value="weighted_random" <?php checked($outcome['mode'], 'weighted_random'); ?>>
                                <strong style="font-size:14px;color:#0f172a;">🎲 1. Random Probability (স্বাভাবিক রেন্ডম)</strong>
                                <p style="margin:8px 0 0 20px;font-size:12px;color:#64748b;">
                                    স্লাইসের প্রোবাবিলিটি শতকরা হার (%) অনুযায়ী ভাগ্যের ওপর নির্ভর করে স্বাভাবিক রেন্ডম বিজয়ী নির্ধারিত হবে।
                                </p>
                            </label>

                            <!-- Option 2: Force Specific Single Prize -->
                            <label style="background:#fff;padding:16px;border-radius:8px;border:2px solid <?php echo ($outcome['mode'] === 'forced_single') ? '#6366f1' : '#e2e8f0'; ?>;cursor:pointer;display:block;">
                                <input type="radio" name="outcome_mode" value="forced_single" <?php checked($outcome['mode'], 'forced_single'); ?>>
                                <strong style="font-size:14px;color:#0f172a;">🎯 2. Force Specific Slice (১টি নির্দিষ্ট প্রাইজ সবসময়)</strong>
                                <p style="margin:8px 0 8px 20px;font-size:12px;color:#64748b;">
                                    যতোজন মানুষ যতোবারই স্পিন করুক, চাকা সবসময় ১০০% নিশ্চিতভাবে নিচে সিলেক্ট করা এই প্রাইজেই গিয়ে থামবে:
                                </p>
                                <div style="margin-left:20px;">
                                    <select name="forced_slice_id" style="width:100%;font-weight:600;padding:5px;">
                                        <?php foreach ($slices as $idx => $s) : 
                                            $s_id = $s['id'] ?? ('slice-' . ($idx + 1));
                                        ?>
                                            <option value="<?php echo esc_attr($s_id); ?>" <?php selected($outcome['forced_slice_id'] ?? '', $s_id); ?>>
                                                👉 <?php echo esc_html($s['label']); ?> (<?php echo esc_html($s['prizeType']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </label>

                            <!-- Option 3: Selected Slices Pool -->
                            <label style="background:#fff;padding:16px;border-radius:8px;border:2px solid <?php echo ($outcome['mode'] === 'selected_pool') ? '#6366f1' : '#e2e8f0'; ?>;cursor:pointer;display:block;">
                                <input type="radio" name="outcome_mode" value="selected_pool" <?php checked($outcome['mode'], 'selected_pool'); ?>>
                                <strong style="font-size:14px;color:#0f172a;">🔀 3. Selected Slices Pool (বাছাইকৃত তালিকা থেকে)</strong>
                                <p style="margin:8px 0 8px 20px;font-size:12px;color:#64748b;">
                                    চাকা শুধুমাত্র নিচে টিক দেওয়া নির্বাচিত প্রাইজগুলোর মধ্যে থেকেই ঘুরবে, অন্য কোনো স্লাইসে কখনই থামবে না:
                                </p>
                                <div style="margin-left:20px;max-height:120px;overflow-y:auto;background:#f8fafc;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;">
                                    <?php 
                                    $allowed = (array)($outcome['allowed_slice_ids'] ?? array());
                                    foreach ($slices as $idx => $s) : 
                                        $s_id = $s['id'] ?? ('slice-' . ($idx + 1));
                                        $is_checked = in_array($s_id, $allowed, true);
                                    ?>
                                        <label style="display:block;margin-bottom:4px;font-size:12px;">
                                            <input type="checkbox" name="allowed_slice_ids[]" value="<?php echo esc_attr($s_id); ?>" <?php checked($is_checked, true); ?>>
                                            <?php echo esc_html($s['label']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- SECTION 3: SLICES TABLE -->
                    <div style="margin-bottom:30px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <h3 style="margin:0;font-size:16px;"><?php esc_html_e('Wheel Slices & Prizes (Click fields to customize)', 'spin-win-woo'); ?></h3>
                            <div id="prob-indicator" style="font-size:13px;font-weight:700;padding:4px 12px;border-radius:20px;background:#e0f2fe;color:#0369a1;">
                                Total Probability: <span id="prob-total">100</span>% / 100%
                            </div>
                        </div>

                        <table class="widefat fixed striped" id="slices-table" style="border-radius:8px;overflow:hidden;">
                            <thead>
                                <tr style="background:#f8fafc;">
                                    <th style="width:65px;"><?php esc_html_e('Color', 'spin-win-woo'); ?></th>
                                    <th><?php esc_html_e('Slice Label (Text on Wheel)', 'spin-win-woo'); ?></th>
                                    <th style="width:190px;"><?php esc_html_e('Prize Type', 'spin-win-woo'); ?></th>
                                    <th style="width:110px;"><?php esc_html_e('Prize Value', 'spin-win-woo'); ?></th>
                                    <th style="width:110px;"><?php esc_html_e('Code Prefix', 'spin-win-woo'); ?></th>
                                    <th style="width:90px;"><?php esc_html_e('Probability', 'spin-win-woo'); ?></th>
                                    <th style="width:90px;"><?php esc_html_e('Stock Limit', 'spin-win-woo'); ?></th>
                                    <th style="width:50px;text-align:center;"><?php esc_html_e('Del', 'spin-win-woo'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="slices-tbody">
                                <?php foreach ($slices as $idx => $s) : ?>
                                    <?php $theme_color = $wheel_theme_palette[$idx % count($wheel_theme_palette)]; ?>
                                    <tr>
                                        <td>
                                            <input type="color" value="<?php echo esc_attr($theme_color[0]); ?>" disabled aria-label="Halloween wheel slice color" title="Halloween theme color" style="width:36px;height:32px;padding:0;cursor:default;opacity:1;border:1px solid #ccc;border-radius:4px;">
                                            <input type="hidden" name="slices[<?php echo $idx; ?>][bgColor]" value="<?php echo esc_attr($theme_color[0]); ?>">
                                            <input type="hidden" name="slices[<?php echo $idx; ?>][textColor]" value="<?php echo esc_attr($theme_color[1]); ?>">
                                            <input type="hidden" name="slices[<?php echo $idx; ?>][stockClaimed]" value="<?php echo esc_attr($s['stockClaimed'] ?? 0); ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="slices[<?php echo $idx; ?>][label]" value="<?php echo esc_attr($s['label']); ?>" style="width:100%;font-weight:600;" required>
                                        </td>
                                        <td>
                                            <select name="slices[<?php echo $idx; ?>][prizeType]" style="width:100%;">
                                                <option value="percentage_discount" <?php selected($s['prizeType'], 'percentage_discount'); ?>>Percentage Discount (%)</option>
                                                <option value="fixed_cart_discount" <?php selected($s['prizeType'], 'fixed_cart_discount'); ?>>Fixed Cart Discount ($)</option>
                                                <option value="fixed_product_discount" <?php selected($s['prizeType'], 'fixed_product_discount'); ?>>Fixed Product Discount ($)</option>
                                                <option value="free_shipping" <?php selected($s['prizeType'], 'free_shipping'); ?>>Free Shipping</option>
                                                <option value="free_gift_product" <?php selected($s['prizeType'], 'free_gift_product'); ?>>Free Gift Product</option>
                                                <option value="try_again" <?php selected($s['prizeType'], 'try_again'); ?>>Try Again / Extra Spin</option>
                                                <option value="none" <?php selected($s['prizeType'], 'none'); ?>>None / Better Luck</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="slices[<?php echo $idx; ?>][prizeValue]" value="<?php echo esc_attr($s['prizeValue']); ?>" style="width:100%;" placeholder="e.g. 10">
                                        </td>
                                        <td>
                                            <input type="text" name="slices[<?php echo $idx; ?>][couponCodePrefix]" value="<?php echo esc_attr($s['couponCodePrefix']); ?>" style="width:100%;text-transform:uppercase;font-family:monospace;">
                                        </td>
                                        <td>
                                            <div style="display:flex;align-items:center;">
                                                <input type="number" step="1" min="0" max="100" class="slice-prob-input" name="slices[<?php echo $idx; ?>][probability]" value="<?php echo esc_attr($s['probability']); ?>" style="width:60px;text-align:center;font-weight:bold;">
                                                <span style="margin-left:3px;">%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="slices[<?php echo $idx; ?>][stockLimit]" value="<?php echo esc_attr($s['stockLimit'] ?? -1); ?>" style="width:70px;text-align:center;" title="-1 for unlimited">
                                        </td>
                                        <td style="text-align:center;">
                                            <button type="button" class="button button-link-delete delete-slice-btn" style="color:#ef4444;font-size:16px;text-decoration:none;" title="Delete slice">&times;</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div style="margin-top:12px;">
                            <button type="button" id="add-slice-btn" class="button" style="display:inline-flex;align-items:center;gap:4px;">
                                <span>➕ Add New Wheel Slice</span>
                            </button>
                        </div>
                    </div>

                    <!-- SECTION 4: FRONTEND APPEARANCE & DISPLAY SETTINGS -->
                    <div style="background:#f8fafc;padding:20px 24px;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:25px;">
                        <h3 style="margin-top:0;font-size:16px;margin-bottom:15px;">🎨 <?php esc_html_e('Storefront Display & Modal Appearance', 'spin-win-woo'); ?></h3>
                        
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:15px;">
                            <div>
                                <label style="font-weight:600;display:block;margin-bottom:5px;"><?php esc_html_e('Modal Headline Text', 'spin-win-woo'); ?></label>
                                <input type="text" name="headline_text" value="<?php echo esc_attr($rules['headline_text'] ?? 'Spin the wheel, win up to $120 off'); ?>" style="width:100%;">
                            </div>
                            <div><strong>Homepage launcher</strong><p style="margin:4px 0;color:#646970;">Icon only; no text is displayed.</p></div>
                        </div>

                        <fieldset style="margin-top:16px;padding:12px;border:1px solid #dcdcde;background:#fff;"><legend style="font-weight:600;">Homepage icon positions (select one or more)</legend>
                            <?php $launcher_positions = (array) ($rules['launcher_positions'] ?? array($rules['floating_btn_pos'] ?? 'bottom_left')); foreach (array('bottom_left'=>'Bottom left','bottom_right'=>'Bottom right','top_left'=>'Top left','top_right'=>'Top right') as $value=>$label) : ?>
                                <label style="margin-right:16px;"><input type="checkbox" name="launcher_positions[]" value="<?php echo esc_attr($value); ?>" <?php checked(in_array($value, $launcher_positions, true)); ?>> <?php echo esc_html($label); ?></label>
                            <?php endforeach; ?>
                        </fieldset>
                        <div style="display:flex;gap:20px;align-items:center;margin-top:12px;"><label>Wheel color <input type="color" name="launcher_color" value="<?php echo esc_attr($rules['launcher_color'] ?? '#1f5b2c'); ?>"></label><label>Border color <input type="color" name="launcher_border" value="<?php echo esc_attr($rules['launcher_border'] ?? '#777777'); ?>"></label><label>Icon size (px) <input type="number" min="48" max="100" name="launcher_size" value="<?php echo esc_attr($rules['launcher_size'] ?? 72); ?>" style="width:75px;"></label></div>
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600;display:block;margin-bottom:5px;"><?php esc_html_e('Modal Subtitle Text', 'spin-win-woo'); ?></label>
                            <textarea name="subheadline_text" rows="2" style="width:100%;"><?php echo esc_textarea($rules['subheadline_text'] ?? 'Enter your details below to unlock instant discounts and gifts on your order.'); ?></textarea>
                        </div>

                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600;display:block;margin-bottom:5px;"><?php esc_html_e('Apply & Go to Cart button URL', 'spin-win-woo'); ?></label>
                            <input type="url" name="cart_button_url" value="<?php echo esc_attr($rules['cart_button_url'] ?? ''); ?>" placeholder="<?php echo esc_attr(function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url()); ?>" style="width:100%;">
                            <p class="description"><?php esc_html_e('Leave blank to use the WooCommerce cart page. The winning slice label and value configured above are shown after a spin.', 'spin-win-woo'); ?></p>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
<div>
                                <label style="font-weight:600;display:block;margin-bottom:5px;"><?php esc_html_e('Auto Popup Delay (Seconds)', 'spin-win-woo'); ?></label>
                                <input type="number" min="0" max="60" name="popup_delay" value="<?php echo esc_attr($rules['popup_delay'] ?? 0); ?>" style="width:100%;">
                            </div>
                            <div style="display:flex;flex-direction:column;justify-content:center;gap:8px;padding-top:14px;">
                                <input type="hidden" name="sound_effects" value="1">
                                <label><input type="checkbox" checked disabled> <?php esc_html_e('Spin & Win Sound Effects (always on)', 'spin-win-woo'); ?></label>
                                <label><input type="checkbox" name="confetti_enabled" value="1" <?php checked($rules['confetti_enabled'] ?? 1, 1); ?>> <?php esc_html_e('Enable Confetti on Winning', 'spin-win-woo'); ?></label>
                            </div>
                        </div>
                    </div>

                    <p class="submit" style="margin:0;">
                        <input type="submit" name="spin_win_save_campaign" class="button button-primary button-large" value="<?php esc_attr_e('💾 Save All Customizations & Slices', 'spin-win-woo'); ?>" style="font-size:15px;padding:6px 24px;height:auto;">
                    </p>
                </form>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tbody = document.getElementById('slices-tbody');
            var addBtn = document.getElementById('add-slice-btn');
            var probTotalSpan = document.getElementById('prob-total');
            var probIndicator = document.getElementById('prob-indicator');

            // Keep the admin preview visually aligned with the Halloween storefront wheel.
            var previewCanvas = document.getElementById('spin-win-admin-wheel-preview');
            var previewCtx = previewCanvas && previewCanvas.getContext('2d');
            var previewBlink = 0;
            var wheelThemePalette = [['#ff7a00','#0d0b0a'],['#0d0b0a','#ff7a00'],['#ff7a00','#0d0b0a'],['#f6e9d7','#0d0b0a']];
            function drawAdminWheel() {
                if (!previewCtx) return;
                var size = 800, center = size / 2, radius = 336;
                var labels = Array.prototype.map.call(tbody.querySelectorAll('input[name$="[label]"]'), function(input) {
                    return input.value || 'PRIZE';
                });
                if (!labels.length) return;
                var step = Math.PI * 2 / labels.length;
                previewCtx.clearRect(0, 0, size, size);
                previewCtx.save(); previewCtx.translate(center, center);
                previewCtx.beginPath(); previewCtx.arc(0, 0, 392, 0, Math.PI * 2); previewCtx.fillStyle = '#0c0805'; previewCtx.fill();
                previewCtx.lineWidth = 10; previewCtx.strokeStyle = '#ff7a00'; previewCtx.stroke();
                for (var light = 0; light < 28; light++) {
                    var lightAngle = light / 28 * Math.PI * 2;
                    previewCtx.beginPath(); previewCtx.arc(Math.cos(lightAngle) * 366, Math.sin(lightAngle) * 366, 7, 0, Math.PI * 2);
                    var lit = (light + previewBlink) % 2 === 0;
                    previewCtx.fillStyle = lit ? '#ffd089' : '#5a3a14'; previewCtx.shadowColor = '#ff9a2e'; previewCtx.shadowBlur = lit ? 14 : 0; previewCtx.fill();
                }
                previewCtx.shadowBlur = 0;
                var colors = [['#ff7a00','#0d0b0a'],['#0d0b0a','#ff7a00'],['#ff7a00','#0d0b0a'],['#f6e9d7','#0d0b0a']];
                labels.forEach(function(label, index) {
                    var start = index * step;
                    previewCtx.beginPath(); previewCtx.moveTo(0,0); previewCtx.arc(0,0,radius,start,start+step); previewCtx.closePath();
                    previewCtx.fillStyle = colors[index % colors.length][0]; previewCtx.fill();
                    previewCtx.lineWidth = 3; previewCtx.strokeStyle = '#f6e9d7'; previewCtx.stroke();
                    previewCtx.save(); previewCtx.rotate(start + step/2); previewCtx.textAlign = 'right'; previewCtx.textBaseline = 'middle';
                    previewCtx.fillStyle = colors[index % colors.length][1];
                    previewCtx.font = (label.length > 12 ? 29 : label.length > 9 ? 36 : 45) + "px 'Permanent Marker', cursive";
                    previewCtx.fillText(label, 316, 0, 245); previewCtx.restore();
                });
                previewCtx.beginPath(); previewCtx.arc(0,0,74,0,Math.PI*2); previewCtx.fillStyle='#ff7a00'; previewCtx.fill();
                previewCtx.lineWidth=5; previewCtx.strokeStyle='#f6e9d7'; previewCtx.stroke();
                previewCtx.fillStyle='#0d0b0a';
                [[-27,-13],[27,-13]].forEach(function(point) { previewCtx.beginPath(); previewCtx.moveTo(point[0]-12,point[1]+12); previewCtx.lineTo(point[0]+12,point[1]+12); previewCtx.lineTo(point[0],point[1]-14); previewCtx.closePath(); previewCtx.fill(); });
                previewCtx.beginPath(); previewCtx.moveTo(-7,5); previewCtx.lineTo(7,5); previewCtx.lineTo(0,15); previewCtx.closePath(); previewCtx.fill();
                previewCtx.beginPath(); previewCtx.moveTo(-40,22); previewCtx.quadraticCurveTo(0,52,40,22); previewCtx.lineTo(29,22); previewCtx.lineTo(22,34); previewCtx.lineTo(11,25); previewCtx.lineTo(0,37); previewCtx.lineTo(-11,25); previewCtx.lineTo(-22,34); previewCtx.lineTo(-29,22); previewCtx.closePath(); previewCtx.fill();
                previewCtx.restore();
            }
            if (previewCanvas) {
                drawAdminWheel();
                if (document.fonts && document.fonts.load) document.fonts.load("45px 'Permanent Marker'").then(drawAdminWheel);
                window.setInterval(function() { previewBlink ^= 1; drawAdminWheel(); }, 600);
                tbody.addEventListener('input', drawAdminWheel);
            }

            function updateProbTotal() {
                var inputs = document.querySelectorAll('.slice-prob-input');
                var total = 0;
                inputs.forEach(function(inp) {
                    total += parseFloat(inp.value) || 0;
                });
                probTotalSpan.textContent = total;
                if (Math.abs(total - 100) < 0.1) {
                    probIndicator.style.background = '#dcfce7';
                    probIndicator.style.color = '#15803d';
                    probIndicator.textContent = 'Total Probability: 100% (Balanced ✅)';
                } else {
                    probIndicator.style.background = '#fee2e2';
                    probIndicator.style.color = '#b91c1c';
                    probIndicator.textContent = 'Total Probability: ' + total + '% / 100% (Must equal 100% ⚠️)';
                }
            }

            tbody.addEventListener('input', function(e) {
                drawAdminWheel();
                if (e.target.classList.contains('slice-prob-input')) {
                    updateProbTotal();
                }
            });

            tbody.addEventListener('change', drawAdminWheel);

            tbody.addEventListener('click', function(e) {
                if (e.target.classList.contains('delete-slice-btn')) {
                    if (tbody.querySelectorAll('tr').length <= 3) {
                        alert('A wheel must have at least 3 slices.');
                        return;
                    }
                    e.target.closest('tr').remove();
                    updateProbTotal();
                    drawAdminWheel();
                }
            });

            addBtn.addEventListener('click', function() {
                var count = tbody.querySelectorAll('tr').length;
                var nextIndex = 0;
                tbody.querySelectorAll('[name]').forEach(function(input) {
                    var match = input.name.match(/^slices\[(\d+)\]/);
                    if (match) nextIndex = Math.max(nextIndex, parseInt(match[1], 10) + 1);
                });
                var themeColor = wheelThemePalette[count % wheelThemePalette.length];
                var tr = document.createElement('tr');
                tr.innerHTML = '<td><input type="color" value="' + themeColor[0] + '" disabled aria-label="Halloween wheel slice color" title="Halloween theme color" style="width:36px;height:32px;padding:0;cursor:default;opacity:1;border:1px solid #ccc;border-radius:4px;"><input type="hidden" name="slices[' + nextIndex + '][bgColor]" value="' + themeColor[0] + '"><input type="hidden" name="slices[' + nextIndex + '][textColor]" value="' + themeColor[1] + '"><input type="hidden" name="slices[' + nextIndex + '][stockClaimed]" value="0"></td>' +
                    '<td><input type="text" name="slices[' + nextIndex + '][label]" value="5% DISCOUNT" style="width:100%;font-weight:600;" required></td>' +
                    '<td><select name="slices[' + nextIndex + '][prizeType]" style="width:100%;"><option value="percentage_discount">Percentage Discount (%)</option><option value="fixed_cart_discount">Fixed Cart Discount ($)</option><option value="fixed_product_discount">Fixed Product Discount ($)</option><option value="free_shipping">Free Shipping</option><option value="free_gift_product">Free Gift Product</option><option value="try_again">Try Again / Extra Spin</option><option value="none">None / Better Luck</option></select></td>' +
                    '<td><input type="text" name="slices[' + nextIndex + '][prizeValue]" value="5" style="width:100%;"></td>' +
                    '<td><input type="text" name="slices[' + nextIndex + '][couponCodePrefix]" value="DISC5" style="width:100%;text-transform:uppercase;font-family:monospace;"></td>' +
                    '<td><div style="display:flex;align-items:center;"><input type="number" step="1" min="0" max="100" class="slice-prob-input" name="slices[' + nextIndex + '][probability]" value="5" style="width:60px;text-align:center;font-weight:bold;"><span style="margin-left:3px;">%</span></div></td>' +
                    '<td><input type="number" name="slices[' + nextIndex + '][stockLimit]" value="-1" style="width:70px;text-align:center;"></td>' +
                    '<td style="text-align:center;"><button type="button" class="button button-link-delete delete-slice-btn" style="color:#ef4444;font-size:16px;text-decoration:none;">&times;</button></td>';
                tbody.appendChild(tr);
                updateProbTotal();
                drawAdminWheel();
            });

            updateProbTotal();
        });
        </script>
        <?php
    }
}
