<?php
if (!defined('ABSPATH')) exit;

/**
 * Define internationalization functionality.
 */
class Spin_Win_i18n {
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'spin-win-woo',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}
