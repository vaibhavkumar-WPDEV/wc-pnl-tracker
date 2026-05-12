<?php if ( ! defined( 'ABSPATH' ) ) exit;

$start_date = sanitize_text_field( $_GET['start'] ?? gmdate( 'Y-m-01' ) );
$end_date   = sanitize_text_field( $_GET['end']   ?? gmdate( 'Y-m-d'  ) );

$engine   = new WCPNL_Data_Engine();
$summary  = $engine->get_summary( $start_date, $end_date );
$products = $engine->get_product_pnl( $start_date, $end_date, 50 );
$currency = get_woocommerce_currency_symbol();

function wcpnl_r_fmt( $n, $c = '' ) {
    return $c . number_format( (float) $n, 2 );
}
?>
<div class="wrap wcpnl-wrap">
    <div class="wcpnl-header">
        <h1 class="wcpnl-title">📋 <?php esc_html_e( 'P&L Reports', 'wc-pnl-tracker' ); ?></h1>
        <div class="wcpnl-header-actions">
            <a href="<?php echo esc_url( add_query_arg( array(
                'action'     => 'wcpnl_export_csv',
                'nonce'      => wp_create_nonce( 'wcpnl_nonce' ),
                'start_date' => $start_date,
                'end_date'   => $end_date,
            ), admin_url( 'admin-ajax.php' ) ) ); ?>"
            class="button button-secondary">⬇ Export CSV</a>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="wcpnl-filter-bar">
        <form method="get" action="">
            <input type="hidden" name="page" value="wcpnl-reports" />
            <div class="wcpnl-filter-row">
                <label>From</label>
                <input type="date" name="start" value="<?php echo esc_attr( $start_date ); ?>" />
                <label>To</label>
                <input type="date" name="end" value="<?php echo esc_attr( $end_date ); ?>" />
                <button type="submit" class="button button-primary">Load Report</button>
                <?php
                $today = gmdate( 'Y-m-d' );
                $presets = array(
                    'This Month'  => array( gmdate( 'Y-m-01' ), $today ),
                    'Last Month'  => array( gmdate( 'Y-m-01', strtotime( 'first day of last month' ) ), gmdate( 'Y-m-t', strtotime( 'last day of last month' ) ) ),
                    'Last 30'     => array( gmdate( 'Y-m-d', strtotime( '-30 days' ) ), $today ),
                    'This Year'   => array( gmdate( 'Y-01-01' ), $today ),
                );
                foreach ( $presets as $label => $dates ) :
                ?>
                <a href="?page=wcpnl-reports&start=<?php echo esc_attr( $dates[0] ); ?>&end=<?php echo esc_attr( $dates[1] ); ?>"
                   class="button"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </div>
        </form>
    </div>

    <!-- P&L Summary Table -->
    <div class="wcpnl-grid-2">
        <div class="wcpnl-card">
            <h2>P&L Summary — <?php echo esc_html( $start_date . ' to ' . $end_date ); ?></h2>
            <table class="wcpnl-table wcpnl-summary-table">
                <tbody>
                    <tr class="wcpnl-row-revenue">
                        <td>Gross Revenue</td>
                        <td class="wcpnl-amount"><?php echo esc_html( wcpnl_r_fmt( $summary['gross_revenue'], $currency ) ); ?></td>
                    </tr>
                    <tr class="wcpnl-row-sub">
                        <td>— Refunds</td>
                        <td class="wcpnl-amount wcpnl-negative">– <?php echo esc_html( wcpnl_r_fmt( $summary['refunds'], $currency ) ); ?></td>
                    </tr>
                    <tr class="wcpnl-row-total">
                        <td><strong>Net Revenue</strong></td>
                        <td class="wcpnl-amount"><strong><?php echo esc_html( wcpnl_r_fmt( $summary['net_revenue'], $currency ) ); ?></strong></td>
                    </tr>
                    <tr class="wcpnl-row-sub">
                        <td>— Cost of Goods (COGS)</td>
                        <td class="wcpnl-amount wcpnl-negative">– <?php echo esc_html( wcpnl_r_fmt( $summary['cogs'], $currency ) ); ?></td>
                    </tr>
                    <tr class="wcpnl-row-total">
                        <td><strong>Gross Profit</strong></td>
                        <td class="wcpnl-amount <?php echo $summary['gross_profit'] >= 0 ? 'wcpnl-positive' : 'wcpnl-negative'; ?>">
                            <strong><?php echo esc_html( wcpnl_r_fmt( $summary['gross_profit'], $currency ) ); ?></strong>
                            <span class="wcpnl-margin-pct"><?php echo esc_html( $summary['gross_margin'] ); ?>%</span>
                        </td>
                    </tr>
                    <tr class="wcpnl-row-sub">
                        <td>— Payment Gateway Fees</td>
                        <td class="wcpnl-amount wcpnl-negative">– <?php echo esc_html( wcpnl_r_fmt( $summary['gateway_fees'], $currency ) ); ?></td>
                    </tr>
                    <tr class="wcpnl-row-highlight">
                        <td><strong>Net Profit</strong></td>
                        <td class="wcpnl-amount <?php echo $summary['net_profit'] >= 0 ? 'wcpnl-positive' : 'wcpnl-negative'; ?>">
                            <strong><?php echo esc_html( wcpnl_r_fmt( $summary['net_profit'], $currency ) ); ?></strong>
                            <span class="wcpnl-margin-pct"><?php echo esc_html( $summary['profit_margin'] ); ?>% margin</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="wcpnl-card">
            <h2>Key Metrics</h2>
            <table class="wcpnl-table wcpnl-metrics-table">
                <tbody>
                    <tr><td>Total Orders</td>             <td><strong><?php echo esc_html( $summary['order_count'] ); ?></strong></td></tr>
                    <tr><td>Average Order Value</td>       <td><?php echo esc_html( wcpnl_r_fmt( $summary['aov'], $currency ) ); ?></td></tr>
                    <tr><td>Total Discounts</td>           <td><?php echo esc_html( wcpnl_r_fmt( $summary['discounts'], $currency ) ); ?></td></tr>
                    <tr><td>Revenue per Day</td>           <td><?php
                        $days = max( 1, (int) ( ( strtotime( $end_date ) - strtotime( $start_date ) ) / DAY_IN_SECONDS ) + 1 );
                        echo esc_html( wcpnl_r_fmt( $summary['net_revenue'] / $days, $currency ) );
                    ?></td></tr>
                    <tr><td>Profit per Day</td>            <td class="<?php echo $summary['net_profit'] >= 0 ? 'wcpnl-positive' : 'wcpnl-negative'; ?>"><?php echo esc_html( wcpnl_r_fmt( $summary['net_profit'] / $days, $currency ) ); ?></td></tr>
                    <tr><td>Gross Margin</td>              <td><?php echo esc_html( $summary['gross_margin'] ); ?>%</td></tr>
                    <tr><td>Net Profit Margin</td>         <td class="<?php echo $summary['profit_margin'] >= 20 ? 'wcpnl-positive' : ( $summary['profit_margin'] >= 0 ? 'wcpnl-warning' : 'wcpnl-negative' ); ?>"><?php echo esc_html( $summary['profit_margin'] ); ?>%</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Product Breakdown -->
    <div class="wcpnl-card">
        <h2>Product P&L Breakdown</h2>
        <?php if ( empty( $products ) ) : ?>
            <p class="wcpnl-muted">No orders found in this date range.</p>
        <?php else : ?>
        <table class="wcpnl-table widefat striped">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty Sold</th>
                    <th>Revenue</th>
                    <th>COGS</th>
                    <th>Profit</th>
                    <th>Margin</th>
                    <th>Unit Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $products as $p ) :
                    $profit_class = $p['profit'] >= 0 ? 'wcpnl-positive' : 'wcpnl-negative';
                    $margin_class = $p['margin'] >= 30 ? 'wcpnl-positive' : ( $p['margin'] >= 0 ? 'wcpnl-warning' : 'wcpnl-negative' );
                ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( get_edit_post_link( $p['id'] ) ); ?>" target="_blank">
                            <?php echo esc_html( $p['name'] ); ?>
                        </a>
                        <?php if ( $p['unit_cost'] <= 0 ) : ?>
                            <span class="wcpnl-badge wcpnl-badge-warning" title="No COGS set — profit may be inaccurate">No COGS</span>
                        <?php endif; ?>
                    </td>
                    <td class="wcpnl-muted"><?php echo esc_html( $p['sku'] ); ?></td>
                    <td><?php echo esc_html( $p['qty'] ); ?></td>
                    <td><?php echo esc_html( wcpnl_r_fmt( $p['revenue'], $currency ) ); ?></td>
                    <td><?php echo esc_html( wcpnl_r_fmt( $p['cogs'], $currency ) ); ?></td>
                    <td class="<?php echo esc_attr( $profit_class ); ?>"><strong><?php echo esc_html( wcpnl_r_fmt( $p['profit'], $currency ) ); ?></strong></td>
                    <td>
                        <span class="wcpnl-margin-bar">
                            <span class="wcpnl-bar-fill <?php echo esc_attr( $margin_class ); ?>" style="width:<?php echo esc_attr( min( 100, abs( $p['margin'] ) ) ); ?>%"></span>
                        </span>
                        <span class="<?php echo esc_attr( $margin_class ); ?>"><?php echo esc_html( $p['margin'] ); ?>%</span>
                    </td>
                    <td class="wcpnl-muted"><?php echo esc_html( $p['unit_cost'] > 0 ? wcpnl_r_fmt( $p['unit_cost'], $currency ) : '—' ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>
