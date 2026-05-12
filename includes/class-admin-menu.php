<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCPNL_Admin_Menu {

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init',            array( $this, 'register_settings' ) );
        // Add COGS field to product pages
        add_action( 'woocommerce_product_options_pricing', array( $this, 'add_cogs_field' ) );
        add_action( 'woocommerce_process_product_meta',    array( $this, 'save_cogs_field' ) );
    }

    public function register_menu() {
        add_menu_page(
            __( 'P&L Tracker', 'wc-pnl-tracker' ),
            __( 'P&L Tracker', 'wc-pnl-tracker' ),
            'manage_woocommerce',
            'wcpnl-dashboard',
            array( $this, 'render_dashboard' ),
            'dashicons-chart-line',
            57
        );

        add_submenu_page( 'wcpnl-dashboard', __( 'Dashboard', 'wc-pnl-tracker' ),  __( 'Dashboard', 'wc-pnl-tracker' ),  'manage_woocommerce', 'wcpnl-dashboard', array( $this, 'render_dashboard' ) );
        add_submenu_page( 'wcpnl-dashboard', __( 'Products',  'wc-pnl-tracker' ),  __( 'Products',  'wc-pnl-tracker' ),  'manage_woocommerce', 'wcpnl-products',  array( $this, 'render_products'  ) );
        add_submenu_page( 'wcpnl-dashboard', __( 'Reports',   'wc-pnl-tracker' ),  __( 'Reports',   'wc-pnl-tracker' ),  'manage_woocommerce', 'wcpnl-reports',   array( $this, 'render_reports'   ) );
        add_submenu_page( 'wcpnl-dashboard', __( 'Settings',  'wc-pnl-tracker' ),  __( 'Settings',  'wc-pnl-tracker' ),  'manage_options',     'wcpnl-settings',  array( $this, 'render_settings'  ) );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'wcpnl' ) === false ) return;

        // Chart.js from CDN
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );

        wp_enqueue_style(  'wcpnl-admin', WCPNL_PLUGIN_URL . 'assets/css/admin.css', array(), WCPNL_VERSION );
        wp_enqueue_script( 'wcpnl-admin', WCPNL_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'chartjs' ), WCPNL_VERSION, true );

        wp_localize_script( 'wcpnl-admin', 'wcpnl', array(
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'wcpnl_nonce' ),
            'currency_symbol' => get_woocommerce_currency_symbol(),
        ) );
    }

    public function register_settings() {
        register_setting( 'wcpnl_settings_group', 'wcpnl_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_settings' ),
        ) );
    }

    public function sanitize_settings( $input ) {
        $statuses = isset( $input['include_statuses'] ) && is_array( $input['include_statuses'] )
            ? array_map( 'sanitize_text_field', $input['include_statuses'] )
            : array( 'wc-completed' );

        return array(
            'gateway_fee_pct'       => (float)  ( $input['gateway_fee_pct']       ?? 2.9  ),
            'gateway_fee_fixed'     => (float)  ( $input['gateway_fee_fixed']      ?? 0.30 ),
            'include_statuses'      => $statuses,
            'include_shipping_cost' => sanitize_text_field( $input['include_shipping_cost'] ?? 'no' ),
        );
    }

    // Add COGS field to WooCommerce product edit page
    public function add_cogs_field() {
        global $post;
        $cost = get_post_meta( $post->ID, '_wc_cogs_cost', true );
        echo '<div class="options_group">';
        woocommerce_wp_text_input( array(
            'id'          => '_wc_cogs_cost',
            'label'       => __( 'Cost of Goods', 'wc-pnl-tracker' ) . ' (' . get_woocommerce_currency_symbol() . ')',
            'placeholder' => '0.00',
            'desc_tip'    => true,
            'description' => __( 'Cost to produce or purchase this product. Used for P&amp;L profit calculations.', 'wc-pnl-tracker' ),
            'value'       => $cost,
            'type'        => 'number',
            'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
        ) );
        echo '</div>';
    }

    public function save_cogs_field( $post_id ) {
        $cost = isset( $_POST['_wc_cogs_cost'] ) ? wc_clean( wp_unslash( $_POST['_wc_cogs_cost'] ) ) : '';
        update_post_meta( $post_id, '_wc_cogs_cost', $cost );
    }

    // Page renderers
    public function render_dashboard() { require_once WCPNL_PLUGIN_DIR . 'admin/pages/dashboard.php'; }
    public function render_products()  { require_once WCPNL_PLUGIN_DIR . 'admin/pages/products.php';  }
    public function render_reports()   { require_once WCPNL_PLUGIN_DIR . 'admin/pages/reports.php';   }
    public function render_settings()  { require_once WCPNL_PLUGIN_DIR . 'admin/pages/settings.php';  }
}
