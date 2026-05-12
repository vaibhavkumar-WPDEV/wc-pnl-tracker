<?php if ( ! defined( 'ABSPATH' ) ) exit;

// Fetch all published products with their COGS
$products = wc_get_products( array(
    'status'  => 'publish',
    'limit'   => 200,
    'orderby' => 'name',
    'order'   => 'ASC',
) );

$currency = get_woocommerce_currency_symbol();
$no_cogs_count = 0;

foreach ( $products as $product ) {
    $cost = get_post_meta( $product->get_id(), '_wc_cogs_cost', true );
    if ( $cost === '' || $cost === false ) {
        $no_cogs_count++;
    }
}
?>
<div class="wrap wcpnl-wrap">
    <div class="wcpnl-header">
        <h1 class="wcpnl-title">📦 <?php esc_html_e( 'Product COGS', 'wc-pnl-tracker' ); ?></h1>
        <div class="wcpnl-header-actions">
            <button type="button" id="wcpnl-bulk-save-btn" class="button button-primary">💾 Save All Changes</button>
        </div>
    </div>

    <?php if ( $no_cogs_count > 0 ) : ?>
    <div class="notice notice-warning" style="margin:0 0 16px;">
        <p>⚠️ <strong><?php echo esc_html( $no_cogs_count ); ?> product(s)</strong> have no Cost of Goods set. Profit calculations will be inaccurate until you add costs.</p>
    </div>
    <?php endif; ?>

    <div id="wcpnl-bulk-save-msg" style="display:none;margin-bottom:12px;"></div>

    <div class="wcpnl-card" style="padding:0;">
        <div class="wcpnl-card-header" style="padding:16px 20px;">
            <h2>All Products
                <span class="wcpnl-count"><?php echo esc_html( count( $products ) ); ?></span>
            </h2>
            <input type="text" id="wcpnl-product-search" placeholder="Search products..." class="wcpnl-search-input" />
        </div>

        <table class="wcpnl-table widefat" id="wcpnl-cogs-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Price (<?php echo esc_html( $currency ); ?>)</th>
                    <th>Cost of Goods (<?php echo esc_html( $currency ); ?>)</th>
                    <th>Est. Margin</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $products as $product ) :
                    $cost    = get_post_meta( $product->get_id(), '_wc_cogs_cost', true );
                    $price   = (float) $product->get_price();
                    $margin  = ( $price > 0 && $cost !== '' )
                        ? round( ( ( $price - (float) $cost ) / $price ) * 100, 1 )
                        : null;
                    $margin_class = $margin === null ? '' : ( $margin >= 30 ? 'wcpnl-positive' : ( $margin >= 0 ? 'wcpnl-warning' : 'wcpnl-negative' ) );
                ?>
                <tr data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-product-name="<?php echo esc_attr( strtolower( $product->get_name() ) ); ?>">
                    <td>
                        <a href="<?php echo esc_url( get_edit_post_link( $product->get_id() ) ); ?>" target="_blank">
                            <?php echo esc_html( $product->get_name() ); ?>
                        </a>
                        <?php if ( $cost === '' || $cost === false ) : ?>
                            <span class="wcpnl-badge wcpnl-badge-warning">No COGS</span>
                        <?php endif; ?>
                    </td>
                    <td class="wcpnl-muted"><?php echo esc_html( $product->get_sku() ?: '—' ); ?></td>
                    <td><?php echo esc_html( $currency . number_format( $price, 2 ) ); ?></td>
                    <td>
                        <div class="wcpnl-cogs-input-wrap">
                            <span class="wcpnl-currency-prefix"><?php echo esc_html( $currency ); ?></span>
                            <input
                                type="number"
                                class="wcpnl-cogs-input"
                                name="costs[<?php echo esc_attr( $product->get_id() ); ?>]"
                                value="<?php echo esc_attr( $cost ); ?>"
                                placeholder="0.00"
                                step="0.01"
                                min="0"
                                data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
                                data-price="<?php echo esc_attr( $price ); ?>"
                            />
                        </div>
                    </td>
                    <td class="wcpnl-margin-cell <?php echo esc_attr( $margin_class ); ?>" data-margin-cell="<?php echo esc_attr( $product->get_id() ); ?>">
                        <?php echo $margin !== null ? esc_html( $margin . '%' ) : '—'; ?>
                    </td>
                    <td>
                        <button type="button"
                            class="button button-small wcpnl-save-single"
                            data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
                            Save
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
