<?php if ( ! defined( 'ABSPATH' ) ) exit;

// Default to current month
$default_start = gmdate( 'Y-m-01' );
$default_end   = gmdate( 'Y-m-d' );

$engine   = new WCPNL_Data_Engine();
$summary  = $engine->get_summary( $default_start, $default_end );
$currency = get_woocommerce_currency_symbol();

function wcpnl_fmt( $n, $currency = '' ) {
    return $currency . number_format( (float) $n, 2 );
}

$profit_class  = $summary['net_profit']  >= 0 ? 'wcpnl-positive' : 'wcpnl-negative';
$margin_class  = $summary['profit_margin'] >= 20 ? 'wcpnl-positive' : ( $summary['profit_margin'] >= 0 ? 'wcpnl-warning' : 'wcpnl-negative' );
?>
<div class="wrap wcpnl-wrap">
    <div class="wcpnl-header">
        <h1 class="wcpnl-title">📈 <?php esc_html_e( 'P&L Dashboard', 'wc-pnl-tracker' ); ?></h1>
        <div class="wcpnl-header-actions">
            <button type="button" id="wcpnl-export-btn" class="button button-secondary">⬇ Export CSV</button>
            <button type="button" id="wcpnl-refresh-btn" class="button">🔄 Refresh</button>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="wcpnl-filter-bar">
        <div class="wcpnl-filter-row">
            <label><?php esc_html_e( 'Period', 'wc-pnl-tracker' ); ?></label>
            <select id="wcpnl-period-preset">
                <option value="this_month" selected>This Month</option>
                <option value="last_month">Last Month</option>
                <option value="last_7">Last 7 Days</option>
                <option value="last_30">Last 30 Days</option>
                <option value="last_90">Last 90 Days</option>
                <option value="this_year">This Year</option>
                <option value="custom">Custom Range</option>
            </select>
            <span id="wcpnl-custom-dates" style="display:none;gap:8px;align-items:center;">
                <input type="date" id="wcpnl-start-date" value="<?php echo esc_attr( $default_start ); ?>" />
                <span>to</span>
                <input type="date" id="wcpnl-end-date" value="<?php echo esc_attr( $default_end ); ?>" />
            </span>
            <button type="button" id="wcpnl-apply-filter" class="button button-primary">Apply</button>
        </div>
        <div id="wcpnl-active-period" class="wcpnl-active-period">
            <?php echo esc_html( $default_start . ' → ' . $default_end ); ?>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="wcpnl-kpi-row" id="wcpnl-kpi-row">
        <div class="wcpnl-kpi-card">
            <div class="wcpnl-kpi-icon">💰</div>
            <div class="wcpnl-kpi-value" id="kpi-revenue"><?php echo esc_html( wcpnl_fmt( $summary['net_revenue'], $currency ) ); ?></div>
            <div class="wcpnl-kpi-label">Net Revenue</div>
            <div class="wcpnl-kpi-sub" id="kpi-orders"><?php echo esc_html( $summary['order_count'] ); ?> orders · AOV <?php echo esc_html( wcpnl_fmt( $summary['aov'], $currency ) ); ?></div>
        </div>
        <div class="wcpnl-kpi-card">
            <div class="wcpnl-kpi-icon">📦</div>
            <div class="wcpnl-kpi-value" id="kpi-cogs"><?php echo esc_html( wcpnl_fmt( $summary['cogs'], $currency ) ); ?></div>
            <div class="wcpnl-kpi-label">Cost of Goods</div>
            <div class="wcpnl-kpi-sub" id="kpi-refunds">Refunds: <?php echo esc_html( wcpnl_fmt( $summary['refunds'], $currency ) ); ?></div>
        </div>
        <div class="wcpnl-kpi-card">
            <div class="wcpnl-kpi-icon">📊</div>
            <div class="wcpnl-kpi-value" id="kpi-gross-profit"><?php echo esc_html( wcpnl_fmt( $summary['gross_profit'], $currency ) ); ?></div>
            <div class="wcpnl-kpi-label">Gross Profit</div>
            <div class="wcpnl-kpi-sub" id="kpi-gross-margin">Margin: <?php echo esc_html( $summary['gross_margin'] ); ?>%</div>
        </div>
        <div class="wcpnl-kpi-card wcpnl-kpi-highlight">
            <div class="wcpnl-kpi-icon">🎯</div>
            <div class="wcpnl-kpi-value <?php echo esc_attr( $profit_class ); ?>" id="kpi-net-profit"><?php echo esc_html( wcpnl_fmt( $summary['net_profit'], $currency ) ); ?></div>
            <div class="wcpnl-kpi-label">Net Profit</div>
            <div class="wcpnl-kpi-sub <?php echo esc_attr( $margin_class ); ?>" id="kpi-margin">Margin: <?php echo esc_html( $summary['profit_margin'] ); ?>%</div>
        </div>
        <div class="wcpnl-kpi-card">
            <div class="wcpnl-kpi-icon">💳</div>
            <div class="wcpnl-kpi-value" id="kpi-fees"><?php echo esc_html( wcpnl_fmt( $summary['gateway_fees'], $currency ) ); ?></div>
            <div class="wcpnl-kpi-label">Gateway Fees</div>
            <div class="wcpnl-kpi-sub" id="kpi-discounts">Discounts: <?php echo esc_html( wcpnl_fmt( $summary['discounts'], $currency ) ); ?></div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="wcpnl-charts-row">
        <!-- Main trend chart -->
        <div class="wcpnl-card wcpnl-chart-main">
            <div class="wcpnl-card-header">
                <h2>Revenue vs Profit Trend</h2>
                <div class="wcpnl-chart-toggle">
                    <button class="wcpnl-toggle-btn active" data-chart-type="daily">Daily</button>
                    <button class="wcpnl-toggle-btn" data-chart-type="monthly">Monthly</button>
                </div>
            </div>
            <div class="wcpnl-chart-wrap">
                <canvas id="wcpnl-trend-chart"></canvas>
            </div>
        </div>

        <!-- Breakdown donut chart -->
        <div class="wcpnl-card wcpnl-chart-side">
            <h2>Revenue Breakdown</h2>
            <div class="wcpnl-chart-wrap wcpnl-chart-donut-wrap">
                <canvas id="wcpnl-breakdown-chart"></canvas>
            </div>
            <div class="wcpnl-breakdown-legend" id="wcpnl-breakdown-legend">
                <div class="wcpnl-legend-item">
                    <span class="wcpnl-legend-dot" style="background:#22c55e;"></span>
                    <span>Net Profit: <strong id="legend-profit"><?php echo esc_html( wcpnl_fmt( $summary['net_profit'], $currency ) ); ?></strong></span>
                </div>
                <div class="wcpnl-legend-item">
                    <span class="wcpnl-legend-dot" style="background:#f59e0b;"></span>
                    <span>COGS: <strong id="legend-cogs"><?php echo esc_html( wcpnl_fmt( $summary['cogs'], $currency ) ); ?></strong></span>
                </div>
                <div class="wcpnl-legend-item">
                    <span class="wcpnl-legend-dot" style="background:#ef4444;"></span>
                    <span>Fees & Refunds: <strong id="legend-fees"><?php echo esc_html( wcpnl_fmt( $summary['gateway_fees'] + $summary['refunds'], $currency ) ); ?></strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Products Table -->
    <div class="wcpnl-card">
        <div class="wcpnl-card-header">
            <h2>Top Products by Profit</h2>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpnl-products' ) ); ?>" class="button button-small">Manage COGS →</a>
        </div>
        <div id="wcpnl-products-table-wrap">
            <table class="wcpnl-table widefat" id="wcpnl-products-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Qty Sold</th>
                        <th>Revenue</th>
                        <th>COGS</th>
                        <th>Profit</th>
                        <th>Margin</th>
                    </tr>
                </thead>
                <tbody id="wcpnl-products-tbody">
                    <tr><td colspan="7" class="wcpnl-loading">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
// Pass initial data to JS
var wcpnlInitialSummary = <?php echo wp_json_encode( $summary ); ?>;
var wcpnlStartDate      = '<?php echo esc_js( $default_start ); ?>';
var wcpnlEndDate        = '<?php echo esc_js( $default_end ); ?>';
</script>
