jQuery( document ).ready( function ( $ ) {

    var trendChart     = null;
    var breakdownChart = null;
    var currentStart   = window.wcpnlStartDate || '';
    var currentEnd     = window.wcpnlEndDate   || '';
    var currency       = wcpnl.currency_symbol || '$';

    // ── Format helpers ────────────────────────────────────────────
    function fmt( n ) {
        return currency + parseFloat( n ).toFixed( 2 ).replace( /\B(?=(\d{3})+(?!\d))/g, ',' );
    }

    function pct( n ) {
        return parseFloat( n ).toFixed( 1 ) + '%';
    }

    // ── Period Presets ────────────────────────────────────────────
    function getPresetDates( preset ) {
        var today = new Date();
        var y = today.getFullYear(), m = today.getMonth(), d = today.getDate();

        var fmt = function ( dt ) {
            return dt.getFullYear() + '-' +
                String( dt.getMonth() + 1 ).padStart( 2, '0' ) + '-' +
                String( dt.getDate() ).padStart( 2, '0' );
        };

        switch ( preset ) {
            case 'this_month':
                return [ fmt( new Date( y, m, 1 ) ),   fmt( today ) ];
            case 'last_month': {
                var lm = new Date( y, m - 1, 1 );
                var lme = new Date( y, m, 0 );
                return [ fmt( lm ), fmt( lme ) ];
            }
            case 'last_7':
                return [ fmt( new Date( y, m, d - 7 ) ), fmt( today ) ];
            case 'last_30':
                return [ fmt( new Date( y, m, d - 30 ) ), fmt( today ) ];
            case 'last_90':
                return [ fmt( new Date( y, m, d - 90 ) ), fmt( today ) ];
            case 'this_year':
                return [ fmt( new Date( y, 0, 1 ) ), fmt( today ) ];
            default:
                return null;
        }
    }

    $( '#wcpnl-period-preset' ).on( 'change', function () {
        var preset = $( this ).val();
        if ( preset === 'custom' ) {
            $( '#wcpnl-custom-dates' ).css( 'display', 'inline-flex' );
        } else {
            $( '#wcpnl-custom-dates' ).hide();
        }
    } );

    $( '#wcpnl-apply-filter' ).on( 'click', function () {
        var preset = $( '#wcpnl-period-preset' ).val();
        var dates;

        if ( preset === 'custom' ) {
            var s = $( '#wcpnl-start-date' ).val();
            var e = $( '#wcpnl-end-date' ).val();
            if ( ! s || ! e ) return;
            dates = [ s, e ];
        } else {
            dates = getPresetDates( preset );
        }

        currentStart = dates[0];
        currentEnd   = dates[1];

        $( '#wcpnl-active-period' ).text( currentStart + ' → ' + currentEnd );
        loadDashboard();
    } );

    // ── Load full dashboard ───────────────────────────────────────
    function loadDashboard() {
        loadSummary();
        loadChartData( 'daily' );
        loadProducts();
    }

    // ── Summary KPIs ──────────────────────────────────────────────
    function loadSummary() {
        $.ajax( {
            url:    wcpnl.ajax_url,
            method: 'POST',
            data: {
                action:     'wcpnl_get_summary',
                nonce:      wcpnl.nonce,
                start_date: currentStart,
                end_date:   currentEnd,
            },
            success: function ( r ) {
                if ( ! r.success ) return;
                var s = r.data;

                $( '#kpi-revenue'      ).text( fmt( s.net_revenue   ) );
                $( '#kpi-cogs'         ).text( fmt( s.cogs           ) );
                $( '#kpi-gross-profit' ).text( fmt( s.gross_profit   ) );
                $( '#kpi-fees'         ).text( fmt( s.gateway_fees   ) );
                $( '#kpi-net-profit'   ).text( fmt( s.net_profit     ) )
                    .removeClass( 'wcpnl-positive wcpnl-negative' )
                    .addClass( s.net_profit >= 0 ? 'wcpnl-positive' : 'wcpnl-negative' );
                $( '#kpi-margin'       ).text( 'Margin: ' + pct( s.profit_margin ) )
                    .removeClass( 'wcpnl-positive wcpnl-warning wcpnl-negative' )
                    .addClass( s.profit_margin >= 20 ? 'wcpnl-positive' : ( s.profit_margin >= 0 ? 'wcpnl-warning' : 'wcpnl-negative' ) );
                $( '#kpi-orders'       ).text( s.order_count + ' orders · AOV ' + fmt( s.aov ) );
                $( '#kpi-gross-margin' ).text( 'Margin: ' + pct( s.gross_margin ) );
                $( '#kpi-refunds'      ).text( 'Refunds: ' + fmt( s.refunds ) );
                $( '#kpi-discounts'    ).text( 'Discounts: ' + fmt( s.discounts ) );

                // Update breakdown chart
                updateBreakdownChart( s );
                $( '#legend-profit' ).text( fmt( s.net_profit ) );
                $( '#legend-cogs'   ).text( fmt( s.cogs ) );
                $( '#legend-fees'   ).text( fmt( s.gateway_fees + s.refunds ) );
            }
        } );
    }

    // ── Trend Chart ───────────────────────────────────────────────
    function loadChartData( type ) {
        $.ajax( {
            url:    wcpnl.ajax_url,
            method: 'POST',
            data: {
                action:     'wcpnl_get_chart_data',
                nonce:      wcpnl.nonce,
                start_date: currentStart,
                end_date:   currentEnd,
                chart_type: type,
            },
            success: function ( r ) {
                if ( ! r.success ) return;

                var data   = r.data.data;
                var labels, revenue, profit, cogs;

                if ( r.data.type === 'monthly' ) {
                    labels  = data.map( function ( d ) { return d.label; } );
                    revenue = data.map( function ( d ) { return d.revenue; } );
                    profit  = data.map( function ( d ) { return d.profit; } );
                    cogs    = data.map( function ( d ) { return d.cogs; } );
                } else {
                    labels  = data.labels;
                    revenue = data.revenue;
                    profit  = data.profit;
                    cogs    = data.cogs;
                }

                renderTrendChart( labels, revenue, profit, cogs );
            }
        } );
    }

    function renderTrendChart( labels, revenue, profit, cogs ) {
        var ctx = document.getElementById( 'wcpnl-trend-chart' );
        if ( ! ctx ) return;

        if ( trendChart ) {
            trendChart.destroy();
        }

        trendChart = new Chart( ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label:           'Revenue',
                        data:            revenue,
                        backgroundColor: 'rgba(59,130,246,0.7)',
                        borderColor:     'rgba(59,130,246,1)',
                        borderWidth:     1,
                        borderRadius:    3,
                    },
                    {
                        label:           'COGS',
                        data:            cogs,
                        backgroundColor: 'rgba(245,158,11,0.6)',
                        borderColor:     'rgba(245,158,11,1)',
                        borderWidth:     1,
                        borderRadius:    3,
                    },
                    {
                        label:           'Net Profit',
                        data:            profit,
                        type:            'line',
                        borderColor:     'rgba(34,197,94,1)',
                        backgroundColor: 'rgba(34,197,94,0.1)',
                        borderWidth:     2.5,
                        pointRadius:     3,
                        tension:         0.3,
                        fill:            false,
                    },
                ],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function ( ctx ) {
                                return ctx.dataset.label + ': ' + currency + parseFloat( ctx.raw ).toFixed( 2 );
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function ( v ) { return currency + v.toLocaleString(); }
                        }
                    }
                }
            }
        } );
    }

    // ── Breakdown donut ───────────────────────────────────────────
    function updateBreakdownChart( s ) {
        var ctx = document.getElementById( 'wcpnl-breakdown-chart' );
        if ( ! ctx ) return;

        var profit   = Math.max( 0, s.net_profit );
        var cogsVal  = Math.max( 0, s.cogs );
        var feesVal  = Math.max( 0, s.gateway_fees + s.refunds );

        if ( breakdownChart ) {
            breakdownChart.data.datasets[0].data = [ profit, cogsVal, feesVal ];
            breakdownChart.update();
            return;
        }

        breakdownChart = new Chart( ctx, {
            type: 'doughnut',
            data: {
                labels:   [ 'Net Profit', 'COGS', 'Fees & Refunds' ],
                datasets: [ {
                    data:            [ profit, cogsVal, feesVal ],
                    backgroundColor: [ '#22c55e', '#f59e0b', '#ef4444' ],
                    borderWidth:     0,
                    hoverOffset:     6,
                } ],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function ( ctx ) {
                                var total = ctx.dataset.data.reduce( function ( a, b ) { return a + b; }, 0 );
                                var pct   = total > 0 ? Math.round( ( ctx.raw / total ) * 100 ) : 0;
                                return ctx.label + ': ' + currency + ctx.raw.toFixed( 2 ) + ' (' + pct + '%)';
                            }
                        }
                    }
                },
                cutout: '65%',
            }
        } );
    }

    // ── Chart toggle ──────────────────────────────────────────────
    $( '.wcpnl-toggle-btn' ).on( 'click', function () {
        $( '.wcpnl-toggle-btn' ).removeClass( 'active' );
        $( this ).addClass( 'active' );
        loadChartData( $( this ).data( 'chart-type' ) );
    } );

    // ── Top Products ──────────────────────────────────────────────
    function loadProducts() {
        var $tbody = $( '#wcpnl-products-tbody' );
        $tbody.html( '<tr><td colspan="7" class="wcpnl-loading">Loading...</td></tr>' );

        $.ajax( {
            url:    wcpnl.ajax_url,
            method: 'POST',
            data: {
                action:     'wcpnl_get_product_pnl',
                nonce:      wcpnl.nonce,
                start_date: currentStart,
                end_date:   currentEnd,
            },
            success: function ( r ) {
                if ( ! r.success || ! r.data.products.length ) {
                    $tbody.html( '<tr><td colspan="7" class="wcpnl-muted" style="text-align:center;padding:20px;">No products found for this period.</td></tr>' );
                    return;
                }

                var html = '';
                r.data.products.forEach( function ( p ) {
                    var profitClass  = p.profit >= 0 ? 'wcpnl-positive' : 'wcpnl-negative';
                    var marginClass  = p.margin >= 30 ? 'wcpnl-positive' : ( p.margin >= 0 ? 'wcpnl-warning' : 'wcpnl-negative' );
                    var noCogs       = p.unit_cost <= 0 ? '<span class="wcpnl-badge wcpnl-badge-warning">No COGS</span>' : '';
                    html += '<tr>';
                    html += '<td>' + p.name + noCogs + '</td>';
                    html += '<td class="wcpnl-muted">' + p.sku + '</td>';
                    html += '<td>' + p.qty + '</td>';
                    html += '<td>' + fmt( p.revenue ) + '</td>';
                    html += '<td>' + fmt( p.cogs ) + '</td>';
                    html += '<td class="' + profitClass + '"><strong>' + fmt( p.profit ) + '</strong></td>';
                    html += '<td class="' + marginClass + '">' + pct( p.margin ) + '</td>';
                    html += '</tr>';
                } );

                $tbody.html( html );
            }
        } );
    }

    // ── Export ────────────────────────────────────────────────────
    $( '#wcpnl-export-btn' ).on( 'click', function () {
        var url = wcpnl.ajax_url + '?action=wcpnl_export_csv&nonce=' + wcpnl.nonce +
            '&start_date=' + currentStart + '&end_date=' + currentEnd;
        window.location = url;
    } );

    // ── Refresh ───────────────────────────────────────────────────
    $( '#wcpnl-refresh-btn' ).on( 'click', function () {
        $.ajax( {
            url:    wcpnl.ajax_url,
            method: 'POST',
            data:   { action: 'wcpnl_clear_cache', nonce: wcpnl.nonce },
            success: loadDashboard,
        } );
    } );

    // ── Products page: search ─────────────────────────────────────
    $( '#wcpnl-product-search' ).on( 'input', function () {
        var term = $( this ).val().toLowerCase();
        $( '#wcpnl-cogs-table tbody tr' ).each( function () {
            var name = $( this ).data( 'product-name' ) || '';
            $( this ).toggle( name.includes( term ) );
        } );
    } );

    // ── Products page: save single ────────────────────────────────
    $( document ).on( 'click', '.wcpnl-save-single', function () {
        var $btn  = $( this );
        var id    = $btn.data( 'product-id' );
        var cost  = $( '.wcpnl-cogs-input[data-product-id="' + id + '"]' ).val();

        $btn.prop( 'disabled', true ).text( '...' );

        $.ajax( {
            url:    wcpnl.ajax_url,
            method: 'POST',
            data:   { action: 'wcpnl_save_cogs', nonce: wcpnl.nonce, product_id: id, cost: cost },
            success: function ( r ) {
                $btn.text( r.success ? '✓ Saved' : '✗ Error' );
                setTimeout( function () { $btn.prop( 'disabled', false ).text( 'Save' ); }, 2000 );
            }
        } );
    } );

    // ── Products page: live margin preview ────────────────────────
    $( document ).on( 'input', '.wcpnl-cogs-input', function () {
        var price = parseFloat( $( this ).data( 'price' ) ) || 0;
        var cost  = parseFloat( $( this ).val() ) || 0;
        var id    = $( this ).data( 'product-id' );
        var $cell = $( '[data-margin-cell="' + id + '"]' );

        if ( price > 0 ) {
            var margin = ( ( price - cost ) / price * 100 ).toFixed( 1 );
            $cell.text( margin + '%' )
                .removeClass( 'wcpnl-positive wcpnl-warning wcpnl-negative' )
                .addClass( margin >= 30 ? 'wcpnl-positive' : ( margin >= 0 ? 'wcpnl-warning' : 'wcpnl-negative' ) );
        }
    } );

    // ── Products page: bulk save ──────────────────────────────────
    $( '#wcpnl-bulk-save-btn' ).on( 'click', function () {
        var $btn  = $( this );
        var costs = {};

        $( '.wcpnl-cogs-input' ).each( function () {
            costs[ $( this ).data( 'product-id' ) ] = $( this ).val();
        } );

        $btn.prop( 'disabled', true ).text( 'Saving...' );

        $.ajax( {
            url:    wcpnl.ajax_url,
            method: 'POST',
            data:   { action: 'wcpnl_bulk_save_cogs', nonce: wcpnl.nonce, costs: costs },
            success: function ( r ) {
                var $msg = $( '#wcpnl-bulk-save-msg' );
                if ( r.success ) {
                    $msg.attr( 'class', 'wcpnl-msg-success' )
                        .text( '✅ ' + r.data.message ).show();
                } else {
                    $msg.attr( 'class', 'wcpnl-msg-error' )
                        .text( '❌ ' + r.data.message ).show();
                }
                $btn.prop( 'disabled', false ).text( '💾 Save All Changes' );
                setTimeout( function () { $msg.fadeOut(); }, 4000 );
            }
        } );
    } );

    // ── Settings: clear cache ─────────────────────────────────────
    $( '#wcpnl-clear-cache-btn' ).on( 'click', function () {
        $.ajax( {
            url:    wcpnl.ajax_url,
            method: 'POST',
            data:   { action: 'wcpnl_clear_cache', nonce: wcpnl.nonce },
            success: function ( r ) {
                $( '#wcpnl-cache-msg' ).text( '✅ Cache cleared!' ).show();
                setTimeout( function () { $( '#wcpnl-cache-msg' ).hide(); }, 3000 );
            }
        } );
    } );

    // ── Init on dashboard ─────────────────────────────────────────
    if ( window.wcpnlInitialSummary && document.getElementById( 'wcpnl-trend-chart' ) ) {
        updateBreakdownChart( window.wcpnlInitialSummary );
        loadChartData( 'daily' );
        loadProducts();
    }

} );
