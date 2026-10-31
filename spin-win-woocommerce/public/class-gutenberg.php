<?php
if (!defined('ABSPATH')) exit;

/**
 * Registers Gutenberg Block for Spin & Win
 */
class Spin_Win_Gutenberg {
    public function __construct() {
        add_action('init', array($this, 'register_block'));
    }

    public function register_block() {
        if (!function_exists('register_block_type')) return;
        register_block_type('spin-win/wheel', array(
            'render_callback' => array($this, 'render_block'),
        ));
    }

    public function render_block($attributes) {
        return '<div class="spin-win-block-container">' . do_shortcode('[spin_win_wheel]') . '</div>';
    }
}
new Spin_Win_Gutenberg();
