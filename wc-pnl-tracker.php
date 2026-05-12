<?php
/**
 * Plugin Name: WooCommerce P&L Tracker
 * Plugin URI:  https://github.com/vaibhavkumar-WPDEV/wc-pnl-tracker
 * Description: Real profit and loss tracking for WooCommerce. Track revenue, cost of goods, fees, and net profit — with charts, product-level breakdown, and CSV export.
 * Version:     1.0.1
 * Author:      Vaibhav Kumar
 * Author URI:  https://www.upwork.com/freelancers/vaibhavkumar
 * License:     GPL v2 or later
 * Text Domain: wc-pnl-tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants immediately — no WooCommerce dependency
define( 'WCPNL_VERSION',    '1.0.1' );
define( 'WCPNL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCPNL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Boot at plugins_loaded priority 20.
 *
 * Root cause of the error:
 * WordPress loads plugins alphabetically. "wc-pnl-tracker" loads
 * BEFORE "woocommerce" so class_exists('WooCommerce') = FALSE at
 * the top of this file. At priority 20 WooCommerce has fully loaded.
 */
add_action( 'plugins_loaded', 'wcpnl_boot', 20 );

function wcpnl_boot() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error is-dismissible"><p>'
                . '<strong>WooCommerce P&amp;L Tracker</strong> requires WooCommerce to be installed and active.'
                . '</p></div>';
        } );
        return;
    }

    require_once WCPNL_PLUGIN_DIR . 'includes/class-data-engine.php';
    require_once WCPNL_PLUGIN_DIR . 'includes/class-admin-menu.php';
    require_once WCPNL_PLUGIN_DIR . 'includes/class-ajax-handler.php';

    new WCPNL_Admin_Menu();
    new WCPNL_Ajax_Handler();
}

register_activation_hook( __FILE__, 'wcpnl_activate' );

function wcpnl_activate() {
    if ( ! get_option( 'wcpnl_settings' ) ) {
        update_option( 'wcpnl_settings', array(
            'gateway_fee_pct'       => 2.9,
            'gateway_fee_fixed'     => 0.30,
            'include_statuses'      => array( 'wc-completed', 'wc-processing' ),
            'include_shipping_cost' => 'no',
        ) );
    }
}
