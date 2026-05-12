<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WCPNL_Data_Engine
 *
 * Queries WooCommerce orders and calculates P&L metrics.
 * Results are cached in WordPress transients for performance.
 *
 * P&L Formula:
 *   Net Revenue    = Gross Revenue - Refunds - Discounts
 *   COGS           = Sum of (product cost × qty) for all line items
 *   Gross Profit   = Net Revenue - COGS
 *   Gateway Fees   = Net Revenue × fee% + (orders × fixed fee)
 *   Net Profit     = Gross Profit - Gateway Fees
 *   Profit Margin  = (Net Profit / Net Revenue) × 100
 */
class WCPNL_Data_Engine {

    private $settings;
    private $currency_symbol;

    public function __construct() {
        $this->settings        = get_option( 'wcpnl_settings', array() );
        $this->currency_symbol = get_woocommerce_currency_symbol();
    }

    // ── Main P&L Summary ──────────────────────────────────────────

    /**
     * Get P&L summary for a date range
     *
     * @param string $start_date  Y-m-d
     * @param string $end_date    Y-m-d
     * @param bool   $force_refresh  Skip cache
     * @return array
     */
    public function get_summary( $start_date, $end_date, $force_refresh = false ) {
        $cache_key = 'wcpnl_summary_' . md5( $start_date . $end_date );

        if ( ! $force_refresh ) {
            $cached = get_transient( $cache_key );
            if ( $cached !== false ) {
                return $cached;
            }
        }

        $orders  = $this->query_orders( $start_date, $end_date );
        $summary = $this->calculate_summary( $orders );

        set_transient( $cache_key, $summary, 15 * MINUTE_IN_SECONDS );

        return $summary;
    }

    /**
     * Get daily P&L breakdown for charts
     *
     * @param string $start_date
     * @param string $end_date
     * @return array  [ 'labels' => [...dates], 'revenue' => [...], 'profit' => [...] ]
     */
    public function get_daily_chart_data( $start_date, $end_date ) {
        $orders = $this->query_orders( $start_date, $end_date );

        // Group by date
        $by_date = array();
        foreach ( $orders as $order ) {
            $date = $order->get_date_created()->date( 'Y-m-d' );
            if ( ! isset( $by_date[ $date ] ) ) {
                $by_date[ $date ] = array();
            }
            $by_date[ $date ][] = $order;
        }

        // Fill in all dates in range
        $all_dates = $this->get_date_range( $start_date, $end_date );
        $labels    = array();
        $revenue   = array();
        $cogs      = array();
        $profit    = array();

        foreach ( $all_dates as $date ) {
            $day_orders   = $by_date[ $date ] ?? array();
            $day_summary  = $this->calculate_summary( $day_orders );
            $labels[]     = gmdate( 'M j', strtotime( $date ) );
            $revenue[]    = round( $day_summary['net_revenue'], 2 );
            $cogs[]       = round( $day_summary['cogs'], 2 );
            $profit[]     = round( $day_summary['net_profit'], 2 );
        }

        return compact( 'labels', 'revenue', 'cogs', 'profit' );
    }

    /**
     * Get P&L per product
     *
     * @param string $start_date
     * @param string $end_date
     * @param int    $limit
     * @return array
     */
    public function get_product_pnl( $start_date, $end_date, $limit = 20 ) {
        $orders   = $this->query_orders( $start_date, $end_date );
        $products = array();

        foreach ( $orders as $order ) {
            $refund_ratio = $order->get_total() > 0
                ? $order->get_total_refunded() / $order->get_total()
                : 0;

            foreach ( $order->get_items() as $item ) {
                $product_id  = $item->get_variation_id() ?: $item->get_product_id();
                $product_obj = $item->get_product();
                if ( ! $product_obj ) continue;

                $qty      = $item->get_quantity();
                $line_rev = $item->get_total(); // after discounts
                $net_rev  = $line_rev * ( 1 - $refund_ratio );
                $unit_cost = (float) get_post_meta( $product_obj->get_id(), '_wc_cogs_cost', true );
                $line_cogs = $unit_cost * $qty;

                if ( ! isset( $products[ $product_id ] ) ) {
                    $products[ $product_id ] = array(
                        'id'       => $product_id,
                        'name'     => $product_obj->get_name(),
                        'sku'      => $product_obj->get_sku() ?: '—',
                        'qty'      => 0,
                        'revenue'  => 0,
                        'cogs'     => 0,
                        'profit'   => 0,
                        'margin'   => 0,
                        'unit_cost'=> $unit_cost,
                    );
                }

                $products[ $product_id ]['qty']     += $qty;
                $products[ $product_id ]['revenue']  += $net_rev;
                $products[ $product_id ]['cogs']     += $line_cogs;
            }
        }

        // Calculate profit and margin
        foreach ( $products as &$p ) {
            $p['profit'] = $p['revenue'] - $p['cogs'];
            $p['margin'] = $p['revenue'] > 0
                ? round( ( $p['profit'] / $p['revenue'] ) * 100, 1 )
                : 0;
            $p['revenue']  = round( $p['revenue'],  2 );
            $p['cogs']     = round( $p['cogs'],     2 );
            $p['profit']   = round( $p['profit'],   2 );
        }
        unset( $p );

        // Sort by profit descending
        usort( $products, fn( $a, $b ) => $b['profit'] <=> $a['profit'] );

        return array_slice( $products, 0, $limit );
    }

    /**
     * Get monthly comparison (last 12 months)
     */
    public function get_monthly_comparison() {
        $months = array();
        for ( $i = 11; $i >= 0; $i-- ) {
            $start = gmdate( 'Y-m-01', strtotime( "-{$i} months" ) );
            $end   = gmdate( 'Y-m-t',  strtotime( "-{$i} months" ) );
            $label = gmdate( 'M Y',    strtotime( "-{$i} months" ) );

            $summary   = $this->get_summary( $start, $end );
            $months[]  = array(
                'label'   => $label,
                'revenue' => round( $summary['net_revenue'], 2 ),
                'profit'  => round( $summary['net_profit'],  2 ),
                'cogs'    => round( $summary['cogs'],         2 ),
            );
        }
        return $months;
    }

    // ── Core Calculation ──────────────────────────────────────────

    private function calculate_summary( array $orders ) {
        $settings     = $this->settings;
        $fee_pct      = (float) ( $settings['gateway_fee_pct']   ?? 2.9 );
        $fee_fixed    = (float) ( $settings['gateway_fee_fixed']  ?? 0.30 );

        $gross_revenue   = 0;
        $refunds         = 0;
        $discounts       = 0;
        $cogs            = 0;
        $order_count     = count( $orders );

        foreach ( $orders as $order ) {
            $gross_revenue += (float) $order->get_total();
            $refunds       += (float) $order->get_total_refunded();
            $discounts     += (float) $order->get_discount_total();

            // COGS from line items
            foreach ( $order->get_items() as $item ) {
                $product = $item->get_product();
                if ( ! $product ) continue;

                $unit_cost = (float) get_post_meta( $product->get_id(), '_wc_cogs_cost', true );
                $cogs     += $unit_cost * (int) $item->get_quantity();
            }
        }

        $net_revenue   = max( 0, $gross_revenue - $refunds );
        $gross_profit  = $net_revenue - $cogs;
        $gateway_fees  = ( $net_revenue * ( $fee_pct / 100 ) ) + ( $order_count * $fee_fixed );
        $net_profit    = $gross_profit - $gateway_fees;
        $profit_margin = $net_revenue > 0
            ? round( ( $net_profit / $net_revenue ) * 100, 2 )
            : 0;
        $gross_margin  = $net_revenue > 0
            ? round( ( $gross_profit / $net_revenue ) * 100, 2 )
            : 0;

        return array(
            'order_count'     => $order_count,
            'gross_revenue'   => round( $gross_revenue,  2 ),
            'refunds'         => round( $refunds,         2 ),
            'discounts'       => round( $discounts,       2 ),
            'net_revenue'     => round( $net_revenue,     2 ),
            'cogs'            => round( $cogs,            2 ),
            'gross_profit'    => round( $gross_profit,    2 ),
            'gross_margin'    => $gross_margin,
            'gateway_fees'    => round( $gateway_fees,    2 ),
            'net_profit'      => round( $net_profit,      2 ),
            'profit_margin'   => $profit_margin,
            'aov'             => $order_count > 0
                ? round( $net_revenue / $order_count, 2 )
                : 0,
        );
    }

    // ── WooCommerce Query ─────────────────────────────────────────

    private function query_orders( $start_date, $end_date ) {
        $settings  = $this->settings;
        $statuses  = $settings['include_statuses'] ?? array( 'wc-completed', 'wc-processing' );

        // Strip 'wc-' prefix for wc_get_orders
        $statuses_clean = array_map( fn( $s ) => ltrim( $s, 'wc-' ), $statuses );

        return wc_get_orders( array(
            'status'       => $statuses_clean,
            'date_created' => $start_date . '...' . $end_date . ' 23:59:59',
            'limit'        => -1,
            'orderby'      => 'date',
            'order'        => 'ASC',
            'return'       => 'objects',
        ) );
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function get_date_range( $start, $end ) {
        $dates   = array();
        $current = strtotime( $start );
        $last    = strtotime( $end );

        // Limit to 90 days for chart display
        $max_days = 90;
        $day_count = 0;

        while ( $current <= $last && $day_count < $max_days ) {
            $dates[]  = gmdate( 'Y-m-d', $current );
            $current  = strtotime( '+1 day', $current );
            $day_count++;
        }

        return $dates;
    }

    /**
     * Clear all cached P&L data
     */
    public static function clear_cache() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcpnl_%'"
        );
    }

    /**
     * Format currency
     */
    public function format_currency( $amount ) {
        return $this->currency_symbol . number_format( (float) $amount, 2 );
    }
}
