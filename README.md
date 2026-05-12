# WooCommerce P&L Tracker

Real profit and loss tracking for WooCommerce. See your actual net profit after deducting cost of goods, payment gateway fees, and refunds — with charts, product-level breakdown, and CSV export.

WooCommerce Analytics shows you revenue. This plugin shows you **profit**.

## What It Does

| WooCommerce Analytics | WC P&L Tracker |
|---|---|
| Shows gross revenue | Shows net revenue after refunds |
| No COGS deduction | Deducts cost of goods per product |
| No gateway fees | Deducts payment gateway fees |
| No margin view | Shows gross and net profit margin |
| No product-level profit | Per-product P&L breakdown |

## Features

- **Dashboard** — 5 KPI cards: Net Revenue, COGS, Gross Profit, Net Profit, Gateway Fees
- **Trend Chart** — Daily and monthly Revenue vs COGS vs Net Profit bar/line chart
- **Breakdown Chart** — Donut showing Net Profit / COGS / Fees as % of revenue
- **Product Table** — Top products ranked by profit with margin bar
- **Products Page** — Bulk COGS editor with live margin preview as you type
- **Reports Page** — Full P&L statement + product breakdown for any date range
- **CSV Export** — Download P&L summary + product breakdown as spreadsheet
- **Settings** — Configure gateway fee % and fixed fee, order statuses to include
- **COGS Field** — Adds "Cost of Goods" field directly to WooCommerce product editor
- **15-min Cache** — Transient caching for large stores, manual clear button

## P&L Formula

```
Gross Revenue
- Refunds
= Net Revenue

- Cost of Goods (COGS)
= Gross Profit

- Payment Gateway Fees (% + fixed per order)
= Net Profit
```

## Installation

1. Download ZIP
2. WordPress Admin → Plugins → Add New → Upload Plugin → Activate
3. Requires WooCommerce to be active

## Setup

### 1. Add Product Costs

**Option A — One by one:**
Go to WooCommerce → Products → Edit Product → scroll to "General" tab → fill in **Cost of Goods**

**Option B — Bulk edit:**
Go to P&L Tracker → Products → edit all costs in one table → Save All

### 2. Configure Gateway Fees

Go to P&L Tracker → Settings:
- **Fee Percentage**: 2.9% for Stripe/PayPal US
- **Fixed Fee**: $0.30 per order for Stripe/PayPal

### 3. View Your P&L

P&L Tracker → Dashboard → select date range → view your real profit

## Screenshots

### Dashboard
5 KPI cards + Revenue/Profit trend chart + Donut breakdown + Top products table

### Products
Bulk COGS editor with live margin preview as you type each cost

### Reports
Full P&L statement with gross/net breakdown + per-product profit table

## COGS Compatibility

This plugin uses the `_wc_cogs_cost` meta key — the same key used by the popular **WooCommerce Cost of Goods** plugin. If you already have costs entered there, they'll work immediately.

## Requirements

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+

## Use Cases

- **eCommerce store owners** — See real profit beyond WooCommerce's revenue numbers
- **Dropshippers** — Track true margin after product + shipping costs
- **Agency clients** — Deliver monthly P&L reports as a value-add service
- **Multi-brand stores** — Filter by date range to compare periods

## This Plugin as a Service

This demonstrates the kind of custom WooCommerce analytics tools I build for clients. Need custom reporting, P&L dashboards, or financial tracking for your WooCommerce store?

Contact me on [Upwork](https://www.upwork.com/freelancers/vaibhavkumar)

## Author

**Vaibhav Kumar** — WooCommerce & AI Integration Developer
Top Rated | 97% JSS | 3,100+ Hours

- Upwork: https://www.upwork.com/freelancers/vaibhavkumar
- GitHub: https://github.com/vaibhavkumar-WPDEV

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Dashboard with KPI cards, trend chart, breakdown donut, product table
- Bulk COGS editor with live margin preview
- Date-filtered P&L reports with product breakdown
- CSV export
- Gateway fee configuration
- 15-minute transient caching
- COGS field on WooCommerce product editor
