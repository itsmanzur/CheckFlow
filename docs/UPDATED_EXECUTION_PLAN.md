# CheckFlow Updated Execution Plan (Doc-Driven)

Last update: 2026-05-06
Scope source: `CheckFlow_Plugin_Roadmap.pdf`

## Current Status Snapshot

### Already done
- Basic plugin bootstrap in `checkflow.php`
- Admin panel shell + scoped CSS/JS (mock-aligned UI)
- JSON i18n (`en_US` / `bn_BD`), per-user locale switch, custom string overrides

### Not done yet (critical)
- Prompt-1 target scaffolding (`composer.json`, `uninstall.php`, loader/activator/deactivator classes, Frontend/Admin split)
- WooCommerce checkout hooks + dynamic front checkout
- AJAX checkout engine
- DB tables for analytics/tracking/payment logs
- Gateways, couriers, server-side pixel tracking
- Drag-drop field editor, bump/upsell, full analytics engine

---

## Phase 0 — Refactor & Foundation

Goal: move from mock-first structure to production plugin architecture.

1. Create core structure
   - `includes/class-checkflow.php` (main orchestrator)
   - `includes/class-loader.php`
   - `includes/class-activator.php`
   - `includes/class-deactivator.php`
2. Add project infra
   - `composer.json` (PSR-4 autoload)
   - `uninstall.php`
   - initial `readme.txt`
3. Split code by domain
   - move admin logic under `includes/Admin/`
   - create `includes/Frontend/` and `includes/Helpers/`
4. Add WooCommerce dependency guard
   - admin notice if WooCommerce inactive
   - disable checkout features safely

Exit criteria:
- plugin boots through orchestrator + loader
- activator/deactivator hooks wired
- no regression in current admin UI

---

## Phase 1 (Free) — Minimum Scope = ALL

User decision: include all free-minimum options: `(a) + (b) + (c)`.

### 1A. Checkout core
- One-page checkout shell
- AJAX totals update without reload
- trust badge rendering

### 1B. Checkout behavior
- popup checkout toggle
- direct checkout (skip cart) toggle

### 1C. Analytics baseline
- create `checkflow_analytics_events` table
- record basic events (`checkout_view`, `checkout_started`, `order_placed`)
- admin funnel placeholder backed by real stored events

Exit criteria:
- Free version has one-page + popup/direct modes + AJAX totals + basic event tracking

---

## Phase 2 — Bangladesh Pro Features

1. Payment gateways
   - bKash, Nagad, SSLCOMMERZ
   - `checkflow_payment_logs` table
2. Courier integration
   - Pathao, RedX, SteadFast
   - courier selection at checkout + order meta booking IDs

Exit criteria:
- paid + courier booking flows work end-to-end in sandbox

---

## Phase 3 — Server-Side Tracking (USP)

- Meta CAPI, Google Enhanced Conversions, TikTok Events API
- trigger from `woocommerce_payment_complete`
- dedupe with stable `event_id`
- failed-event retry queue + logs (`checkflow_tracking_events`)

Exit criteria:
- reliable server-side purchase events, retriable failures, admin visibility

---

## Phase 4 — Conversion Features

- Drag-and-drop field editor (React)
- Order bump + one-click upsell + rules engine
- sales performance storage/reporting

---

## Phase 5 — Full Analytics Dashboard

- funnel, revenue trends, payment breakdown, abandonment insights
- date ranges, CSV export, periodic refresh

---

## Phase 6 — Hardening & Release

- security audit checklist
- PHPUnit/integration tests
- WP.org submission readiness

---

## Step-by-Step Build Order (Strict)

1. Phase 0
2. Phase 1A
3. Phase 1B
4. Phase 1C
5. Phase 2
6. Phase 3
7. Phase 4
8. Phase 5
9. Phase 6

No phase skip.
