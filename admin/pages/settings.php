<?php if ( ! defined( 'ABSPATH' ) ) exit;
$settings = get_option( 'wcpnl_settings', array() );
$statuses = wc_get_order_statuses();
$selected_statuses = $settings['include_statuses'] ?? array( 'wc-completed' );
?>
<div class="wrap wcpnl-wrap">
    <h1 class="wcpnl-title">⚙️ <?php esc_html_e( 'P&L Settings', 'wc-pnl-tracker' ); ?></h1>

    <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><strong>Settings saved.</strong></p></div>
    <?php endif; ?>

    <div class="wcpnl-grid-2">
        <div class="wcpnl-card">
            <form method="post" action="options.php">
                <?php settings_fields( 'wcpnl_settings_group' ); ?>

                <h2>💳 Payment Gateway Fees</h2>
                <p class="wcpnl-muted">Enter your gateway's fee structure. These are deducted from gross profit to calculate net profit.</p>
                <table class="form-table">
                    <tr>
                        <th><label for="wcpnl_fee_pct">Fee Percentage (%)</label></th>
                        <td>
                            <input type="number" id="wcpnl_fee_pct" name="wcpnl_settings[gateway_fee_pct]"
                                value="<?php echo esc_attr( $settings['gateway_fee_pct'] ?? 2.9 ); ?>"
                                step="0.01" min="0" max="20" style="width:100px;" /> %
                            <p class="description">Stripe/PayPal: 2.9% · Stripe International: 3.9%</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wcpnl_fee_fixed">Fixed Fee Per Order</label></th>
                        <td>
                            <span><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                            <input type="number" id="wcpnl_fee_fixed" name="wcpnl_settings[gateway_fee_fixed]"
                                value="<?php echo esc_attr( $settings['gateway_fee_fixed'] ?? 0.30 ); ?>"
                                step="0.01" min="0" style="width:100px;" />
                            <p class="description">Stripe/PayPal: $0.30 per transaction</p>
                        </td>
                    </tr>
                </table>

                <hr>
                <h2>📋 Order Statuses to Include</h2>
                <p class="wcpnl-muted">Select which order statuses count toward revenue and profit calculations.</p>
                <div class="wcpnl-checkbox-group">
                    <?php foreach ( $statuses as $slug => $label ) : ?>
                    <label class="wcpnl-checkbox-label">
                        <input type="checkbox"
                            name="wcpnl_settings[include_statuses][]"
                            value="<?php echo esc_attr( $slug ); ?>"
                            <?php checked( in_array( $slug, $selected_statuses, true ) ); ?> />
                        <?php echo esc_html( $label ); ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <hr>
                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>

        <div>
            <div class="wcpnl-card">
                <h2>📖 How P&L Is Calculated</h2>
                <div class="wcpnl-formula">
                    <div class="wcpnl-formula-row wcpnl-formula-revenue">Gross Revenue</div>
                    <div class="wcpnl-formula-row wcpnl-formula-sub">− Refunds</div>
                    <div class="wcpnl-formula-row wcpnl-formula-total">= Net Revenue</div>
                    <div class="wcpnl-formula-row wcpnl-formula-sub">− Cost of Goods (COGS)</div>
                    <div class="wcpnl-formula-row wcpnl-formula-total">= Gross Profit</div>
                    <div class="wcpnl-formula-row wcpnl-formula-sub">− Gateway Fees (% + fixed × orders)</div>
                    <div class="wcpnl-formula-row wcpnl-formula-highlight">= Net Profit</div>
                </div>
                <p class="wcpnl-muted" style="margin-top:12px;">
                    Set product costs in <strong>WooCommerce → Products → Edit Product → Cost of Goods</strong>, or bulk-edit them in <a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpnl-products' ) ); ?>">Products tab</a>.
                </p>
            </div>

            <div class="wcpnl-card">
                <h2>🗑️ Cache</h2>
                <p class="wcpnl-muted">P&L data is cached for 15 minutes. Clear the cache if you need up-to-the-minute data.</p>
                <button type="button" id="wcpnl-clear-cache-btn" class="button button-secondary">Clear P&L Cache</button>
                <span id="wcpnl-cache-msg" style="display:none;margin-left:8px;color:#00a32a;"></span>
            </div>

            <div class="wcpnl-card">
                <h2>ℹ️ About</h2>
                <p>WooCommerce P&L Tracker gives you real profit visibility beyond what WooCommerce Analytics shows — including cost of goods, gateway fee deductions, and true margin per product.</p>
                <p>Built by <strong>Vaibhav Kumar</strong> — <a href="https://www.upwork.com/freelancers/vaibhavkumar" target="_blank">Upwork Profile</a></p>
            </div>
        </div>
    </div>
</div>
