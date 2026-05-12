<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCPNL_Ajax_Handler {

    public function __construct() {
        $actions = array(
            'wcpnl_get_summary',
            'wcpnl_get_chart_data',
            'wcpnl_get_product_pnl',
            'wcpnl_save_cogs',
            'wcpnl_bulk_save_cogs',
            'wcpnl_export_csv',
            'wcpnl_clear_cache',
        );
        foreach ( $actions as $action ) {
            add_action( "wp_ajax_{$action}", array( $this, $action ) );
        }
    }

    private function check() {
        check_ajax_referer( 'wcpnl_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
    }

    private function engine() {
        return new WCPNL_Data_Engine();
    }

    private function get_dates() {
        $start = sanitize_text_field( $_POST['start_date'] ?? gmdate( 'Y-m-01' ) );
        $end   = sanitize_text_field( $_POST['end_date']   ?? gmdate( 'Y-m-d'  ) );
        return array( $start, $end );
    }

    public function wcpnl_get_summary() {
        $this->check();
        list( $start, $end ) = $this->get_dates();
        $summary = $this->engine()->get_summary( $start, $end );
        wp_send_json_success( $summary );
    }

    public function wcpnl_get_chart_data() {
        $this->check();
        list( $start, $end ) = $this->get_dates();

        $type = sanitize_text_field( $_POST['chart_type'] ?? 'daily' );

        if ( $type === 'monthly' ) {
            $data = $this->engine()->get_monthly_comparison();
            wp_send_json_success( array( 'type' => 'monthly', 'data' => $data ) );
        } else {
            $data = $this->engine()->get_daily_chart_data( $start, $end );
            wp_send_json_success( array( 'type' => 'daily', 'data' => $data ) );
        }
    }

    public function wcpnl_get_product_pnl() {
        $this->check();
        list( $start, $end ) = $this->get_dates();
        $products = $this->engine()->get_product_pnl( $start, $end );
        wp_send_json_success( array( 'products' => $products ) );
    }

    public function wcpnl_save_cogs() {
        $this->check();
        $product_id = absint( $_POST['product_id'] ?? 0 );
        $cost       = wc_clean( wp_unslash( $_POST['cost'] ?? '' ) );

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => 'Invalid product ID.' ) );
        }

        update_post_meta( $product_id, '_wc_cogs_cost', $cost );
        WCPNL_Data_Engine::clear_cache();

        wp_send_json_success( array(
            'message'    => 'Cost saved.',
            'product_id' => $product_id,
            'cost'       => $cost,
        ) );
    }

    public function wcpnl_bulk_save_cogs() {
        $this->check();

        $costs = $_POST['costs'] ?? array();
        if ( ! is_array( $costs ) ) {
            wp_send_json_error( array( 'message' => 'Invalid data.' ) );
        }

        $saved = 0;
        foreach ( $costs as $product_id => $cost ) {
            $product_id = absint( $product_id );
            $cost       = wc_clean( wp_unslash( $cost ) );
            if ( $product_id > 0 ) {
                update_post_meta( $product_id, '_wc_cogs_cost', $cost );
                $saved++;
            }
        }

        WCPNL_Data_Engine::clear_cache();
        wp_send_json_success( array( 'message' => "{$saved} products updated.", 'saved' => $saved ) );
    }

    public function wcpnl_export_csv() {
        check_ajax_referer( 'wcpnl_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Permission denied.' );
        }

        list( $start, $end ) = $this->get_dates();
        $engine   = $this->engine();
        $summary  = $engine->get_summary( $start, $end );
        $products = $engine->get_product_pnl( $start, $end );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="pnl-' . $start . '-to-' . $end . '.csv"' );
        header( 'Pragma: no-cache' );

        $output = fopen( 'php://output', 'w' );

        // Summary section
        fputcsv( $output, array( 'P&L Summary', $start . ' to ' . $end ) );
        fputcsv( $output, array() );
        fputcsv( $output, array( 'Metric', 'Amount' ) );

        $currency = get_woocommerce_currency_symbol();
        fputcsv( $output, array( 'Orders',         $summary['order_count'] ) );
        fputcsv( $output, array( 'Gross Revenue',  $currency . $summary['gross_revenue']  ) );
        fputcsv( $output, array( 'Refunds',        $currency . $summary['refunds']        ) );
        fputcsv( $output, array( 'Net Revenue',    $currency . $summary['net_revenue']    ) );
        fputcsv( $output, array( 'Cost of Goods',  $currency . $summary['cogs']           ) );
        fputcsv( $output, array( 'Gross Profit',   $currency . $summary['gross_profit']   ) );
        fputcsv( $output, array( 'Gross Margin',   $summary['gross_margin'] . '%'         ) );
        fputcsv( $output, array( 'Gateway Fees',   $currency . $summary['gateway_fees']   ) );
        fputcsv( $output, array( 'Net Profit',     $currency . $summary['net_profit']     ) );
        fputcsv( $output, array( 'Profit Margin',  $summary['profit_margin'] . '%'        ) );

        fputcsv( $output, array() );

        // Product breakdown
        fputcsv( $output, array( 'Product Breakdown' ) );
        fputcsv( $output, array( 'Product', 'SKU', 'Qty Sold', 'Revenue', 'COGS', 'Profit', 'Margin %' ) );

        foreach ( $products as $p ) {
            fputcsv( $output, array(
                $p['name'],
                $p['sku'],
                $p['qty'],
                $currency . $p['revenue'],
                $currency . $p['cogs'],
                $currency . $p['profit'],
                $p['margin'] . '%',
            ) );
        }

        fclose( $output );
        exit;
    }

    public function wcpnl_clear_cache() {
        $this->check();
        WCPNL_Data_Engine::clear_cache();
        wp_send_json_success( array( 'message' => 'Cache cleared.' ) );
    }
}
