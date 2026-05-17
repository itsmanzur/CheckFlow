# CheckFlow Updated Execution Plan

Last update: 2026-05-06  
Primary scope source: `CheckFlow_Plugin_Roadmap Upadet.pdf`  
Previous source: `CheckFlow_Plugin_Roadmap-old.pdf`  
Approved admin UI reference: `CheckFlow_Admin_Panel.html`

## 1. Product Direction From Updated Roadmap

CheckFlow is a WooCommerce one-page checkout plugin for Bangladesh-first ecommerce, then South Asia/global markets.

Core promise:
- One-page, mobile-first, fast checkout.
- bKash, Nagad, Rocket, SSLCOMMERZ native/pro gateway support.
- Pathao, RedX, SteadFast courier booking support.
- Server-side ad tracking that does not break when checkout/thank-you URLs change.
- Admin dashboard exactly matching the approved dark CheckFlow design.

Pricing/edition direction:
- Free: AJAX checkout, mobile-first design, popup/slide checkout, direct checkout, trust badges, one basic template.
- Pro: field editor, order bump, upsell, premium templates, BD payments/couriers, analytics, multilingual, tracking, express pay, urgency/coupon features.
- Agency: A/B testing, white label, team access/roles, priority support.

## 2. Updated Roadmap Phases

### Phase 1 - Build & Foundation, Months 1-3

Month 1:
- Production plugin scaffold: OOP structure, loader, activator/deactivator, Composer PSR-4, WP/Woo/PHP version requirements.
- WooCommerce checkout hooks, nonce-protected AJAX handlers, input sanitization.
- Frontend checkout UI using Alpine.js, mobile-first CSS, custom properties.
- Core free checkout features: popup checkout, direct checkout, AJAX totals, trust badges.

Month 2:
- WordPress admin settings panel using the approved CheckFlow Admin Panel design.
- React-based drag-and-drop field editor with 20+ field types and conditional logic.
- Template system with 5 premium templates, switcher, custom CSS support.
- PHPUnit/browser QA and PHP 7.4-8.3 compatibility.

Month 3:
- Conditional asset loading, minify/lazy loading, target checkout load under 1 second.
- Security audit: CSRF, XSS, SQL injection, rate limiting, reCAPTCHA v3.
- WordPress.org readme, screenshots, SVN submission readiness.
- Bangladesh community launch content.

### Phase 2 - Pro Launch & Bangladesh Features, Months 4-6

- bKash Payment API v1.2.0 with native checkout button and refund support.
- Nagad Merchant API, Rocket support, strong error handling.
- SSLCOMMERZ with card/mobile wallet support, 3D Secure, multi-currency.
- Pathao, RedX, SteadFast courier integrations with zone/area lookup, pricing, booking, tracking, COD support.
- Order bump, one-click upsell, analytics dashboard.
- Server-side Meta CAPI, Google Enhanced Conversions, TikTok Events API.
- Freemius/licensing, auto-update, AppSumo LTD, Bengali/English docs.

### Phase 3 - Global Growth, Months 7-12

- Product Hunt launch, SEO content around broken WooCommerce pixel tracking.
- Affiliate program.
- Agency plan.
- A/B testing.
- Year-1 targets: 500+ users, $5k MRR, 4.8+ WordPress.org rating.

### Phase 4 - Scale, Year 2

- India: Razorpay, Paytm, UPI, Hindi docs.
- Pakistan: JazzCash, EasyPaisa, Urdu UI.
- SaaS analytics/team collaboration.
- Enterprise tier with custom integrations and SLA.

## 3. Cursor Prompt Implementation Order

We will implement the updated roadmap by prompt/module, but keep the approved admin design as a strict visual target.

1. Prompt 1 - Plugin Scaffold & Architecture
2. Prompt 11 - Admin Panel UI Design System, matching `CheckFlow_Admin_Panel.html` 100%
3. Prompt 2 - AJAX Checkout Engine
4. Prompt 9 - Frontend Checkout UI with Alpine.js
5. Prompt 8 - Analytics data collection/dashboard foundation
6. Prompt 6 - Drag-and-drop field editor
7. Prompt 3 - bKash, Nagad, Rocket, SSLCOMMERZ gateways
8. Prompt 4 - Pathao, RedX, SteadFast courier APIs
9. Prompt 5 - Server-side tracking
10. Prompt 7 - Order bump and upsell system
11. Prompt 10 - Tests, security audit, WordPress.org package

Reason for putting Prompt 11 early: the approved admin panel must be implemented exactly, and later analytics/settings modules should plug into that UI instead of redesigning it.

## 4. Current Plugin Audit

### Already Done

- `checkflow.php` exists with plugin constants, activation/deactivation hooks, and bootstrap.
- Core class files exist:
  - `includes/class-checkflow.php`
  - `includes/class-loader.php`
  - `includes/class-activator.php`
  - `includes/class-deactivator.php`
  - `includes/class-checkflow-i18n.php`
  - `includes/class-checkflow-admin.php`
- Frontend placeholder/domain files exist:
  - `includes/Frontend/class-assets.php`
  - `includes/Frontend/class-ajax.php`
  - `includes/Frontend/class-checkout.php`
  - `public/css/checkflow.css`
  - `public/js/checkflow-ajax.js`
- Admin shell exists in `views/admin-shell.php`.
- Admin CSS/JS exists in `assets/admin.css` and `assets/admin.js`.
- JSON i18n exists in `i18n/en_US.json` and `i18n/bn_BD.json`.
- Per-user admin locale switch and string override editor exist.
- WooCommerce inactive admin notice exists.
- Frontend checkout has quantity update, coupon apply/remove, field validation, shipping-method refresh, and guarded place-order AJAX handlers.
- Frontend checkout now loads `public/js/checkflow.js` as a WooCommerce-compatible app layer with Alpine-compatible registration, vanilla fallback, and block-checkout detection.
- Current checkout direction is to polish the existing WooCommerce checkout/block layout instead of replacing it with a custom template.
- Checkout AJAX responses now use a consistent `success`, `data`, `message`, `errors` shape for CheckFlow-owned endpoints.
- Checkout AJAX requests now have nonce validation, per-IP/session rate limiting, and WP_DEBUG security logging for invalid nonce/rate-limit events.
- Trust badges and checkout shell intro are partially implemented.

### What Is Wrong / Incomplete

- Roadmap Prompt 1 asks for namespaced PSR-4 classes, but the plugin currently uses global class names and manual `require_once`.
- `composer.json` declares PSR-4 autoload, but no Composer autoload is actually loaded.
- Prompt 1 folder structure asks for `admin/css`, `admin/js`, `admin/partials`, and `languages`; current code uses `assets`, `views`, and `i18n`.
- Admin page slug is `checkflow`; updated Prompt 11 expects pages like `checkflow-dashboard`, `checkflow-orders`, `checkflow-field-editor`, etc.
- Admin design is close to the HTML mockup, but not guaranteed 100%:
  - WordPress admin menu is not hidden for CheckFlow pages.
  - `#wpcontent` margin is not fully reset.
  - CSS variables use `--bg`, `--s1`, etc.; Prompt 11 expects scoped `--cf-bg`, `--cf-s1`, etc.
  - CSS files are not in the Prompt 11 requested paths.
  - Several sections are dashboard stubs instead of real screens/submenus.
- Admin dashboard cards, funnel, payment mix, courier snapshot, mini chart, and recent orders now read from WooCommerce orders plus CheckFlow local event data.
- Quick setting toggles now save via `wp_ajax_checkflow_toggle_setting`, but each setting still needs explicit feature wiring before it can be treated as production-complete.
- `checkflow_get_stats` AJAX now returns WooCommerce-backed daily order chart data.
- Activator now creates the CheckFlow local pixel event log table, but remaining planned tables are not implemented:
  - `checkflow_analytics_events`
  - `checkflow_payment_logs`
  - `checkflow_sales_performance`
- AJAX checkout engine first pass is implemented, but still needs full browser QA with real product/cart/payment scenarios.
- `checkflow_place_order` delegates to WooCommerce checkout processing; final payment/order behavior must be tested with real gateways before release.
- Frontend checkout app first pass exists and is now block-aware, but still needs real browser QA for the current WooCommerce Blocks checkout page.
- Direct Checkout first pass is implemented with an admin quick setting, non-AJAX add-to-cart redirect, and AJAX add-to-cart redirect after WooCommerce confirms the cart add.
- Field Editor is now a card-based builder for WooCommerce core checkout fields: admin can save label, required state, visibility, manual priority, arrow ordering, and drag/drop ordering.
- Field Editor custom-field first pass supports Text, Select, Checkbox, Date, and Textarea fields; custom field values are stored as order meta.
- Quick Settings functional mapping status:
  - `direct_checkout`: implemented; controls skip-cart redirect for non-AJAX and AJAX add-to-cart flows.
  - `guest_checkout`: implemented; maps to WooCommerce native guest checkout/registration-required behavior.
  - `urgency_timer`: implemented as a checkout countdown module controlled by the setting.
  - `order_bump`: implemented as a configured-product engine with admin product picker, offer preview, placement, targeting rules, live rule summary, and checkout-side add-to-cart guard.
  - `popup_checkout`: implemented as a storefront add-to-cart modal with checkout/cart/continue actions; it does not hijack final payment submission.
  - `slide_checkout`: implemented as a storefront side drawer. Conflict rule: Popup Checkout has priority; Slide-in Checkout runs only when Popup Checkout is off.
  - `recaptcha`: implemented for classic checkout when site/secret keys are configured via options or filters; with no keys it is a safe no-op and does not block orders.
- Popup modal checkout and slide-in panel checkout are implemented as add-to-cart conversion modules, not final payment submission replacements.
- Direct checkout/skip cart is implemented and browser-verified by manual QA.
- Field editor conditional logic and advanced field-type settings are not implemented yet.
- Template system is not implemented.
- bKash/Nagad/Rocket/SSLCOMMERZ gateways are not implemented.
- Pathao/RedX/SteadFast courier integrations are not implemented.
- Pixel Tracking foundation is implemented:
  - CheckFlow Local Event Log table.
  - Storefront browser events for `PageView`, `ViewContent`, `AddToCart`, `InitiateCheckout`, and `Purchase`.
  - Purchase duplicate guard.
  - Meta Pixel setup and browser-fire foundation.
  - Google Ads / GA4 and TikTok setup UI placeholders.
  - Advanced event ON/OFF controls, retention days, test event, export CSV, and clear log actions.
  - Interactive local tracking insights.
  - Real browser firing for Meta Pixel, Google Ads / GA4, and TikTok Pixel.
- Meta CAPI, Google Enhanced Conversions, and TikTok Events API server-side firing are not implemented yet.
- Order bump now has a lock-ready admin foundation: product picker, copy preview, placement, cart/customer/location/payment targeting rules, live rule summary, checklist cues, and frontend rule enforcement; upsell rules are still pending.
- Order Bump checkout QA note: current saved rules require cart product `#162 - Download Natural`; with that product in cart and subtotal over the minimum, the configured `#155 - Gift Grey Gel` offer renders, AJAX-adds to the checkout cart, refresh hides the offer to prevent duplicate add, and the Quick Settings `order_bump` state now syncs with the dedicated Order Bump enable toggle.
- Upsell Funnel now has a saved admin rules UI foundation for pre-purchase/post-purchase flows: offer product, optional downsell product, copy, discount placeholder, cart/product/category/country/payment/customer rules, timing, live preview, rule chips, checklist, safe-mode guidance, and clear save feedback. Customer-facing execution is intentionally deferred so WooCommerce payment/order flow stays safe.
- Dashboard analytics foundation is implemented using WooCommerce order data and CheckFlow local event logs; dedicated analytics event tables are still pending.
- Tests, security audit docs, checklist docs, screenshots, `.pot`/`.po` files are not implemented.
- `checkflow.php` metadata still has placeholder Plugin URI and a mojibake dash in the description.
- `Domain Path` says `/languages`, but translations are currently JSON in `/i18n`.
- `includes/Admin/class-settings.php`, helper logger/security files are empty placeholders.
- Git working tree currently shows deleted old roadmap files and untracked new/old PDFs; do not clean this up unless intentionally requested.

## 5. Immediate Build Checklist

### Milestone A - Admin Design 100% Parity

- [ ] Move/align admin assets to Prompt 11 structure or document why existing `assets/`/`views/` structure will be kept.
- [ ] Rename/scaffold admin module as `includes/Admin/class-admin.php` or map current `class-checkflow-admin.php` cleanly.
- [x] Add real CheckFlow admin page slugs/submenus: dashboard, orders, pixel, courier, field editor, templates, order bump, upsell, payment, settings.
- [x] Hide WordPress left admin menu only on CheckFlow pages.
- [x] Reset `#wpcontent`, `#wpbody-content`, and page margins only on CheckFlow pages.
- [x] Convert admin CSS variables to Prompt 11 `--cf-*` names or add a compatibility layer.
- [x] Compare `CheckFlow_Admin_Panel.html` against `views/admin-shell.php` and `assets/admin.css` line by line.
- [x] Replace all stub panes with first-pass real screens matching the mockup layout.
- [x] Make toggles save to `wp_options` via nonce-protected AJAX.
- [x] Fix LocalWP admin AJAX same-origin handling for `gsttest.local:10040`.
- [x] Add `checkflow_get_stats` AJAX with WooCommerce-backed daily chart data.
- [x] Browser-test the admin page at desktop and mobile widths and fix spacing/overflow differences.

### Milestone B - Architecture Cleanup

- [ ] Decide whether to keep global classes for now or migrate to PSR-4 namespaces.
- [ ] If using PSR-4, run Composer autoload and update bootstrap.
- [ ] Fix plugin metadata, Plugin URI, Requires WP/Woo details, and text domain paths.
- [ ] Add real helper methods to `class-security.php` and `class-logger.php`.
- [x] Add first DB table creation to activator using `dbDelta` for CheckFlow local pixel event log.
- [ ] Add remaining planned DB tables using `dbDelta`.
- [ ] Add uninstall cleanup policy/options.

### Milestone C - Free Checkout Core

- [x] Complete AJAX handlers: update totals, coupon apply/remove, validate field, get shipping methods, place order.
- [x] Add rate limiting: max 10 checkout AJAX requests per minute per IP/session.
- [x] Standardize CheckFlow-owned AJAX responses with `success`, `data`, `message`, `errors`.
- [x] Add frontend inline validation feedback for checkout fields.
- [x] Add frontend shipping-method refresh hook when address fields change.
- [x] Build `public/js/checkflow.js` WooCommerce-compatible checkout app with block detection, Alpine-compatible registration, and vanilla fallback.
- [x] Build first-pass standard one-page layout controls with sticky order summary support.
- [x] Preserve WooCommerce Blocks/native payment flow; do not hijack final place-order submission.
- [x] Add current checkout layout polish for page spacing, form fields, order summary, buttons, and mobile stacking.
- [ ] Browser-test checkout with a real product in cart: quantity update, coupon apply/remove, field errors, shipping recalculation, and place order.
- [x] Add direct checkout/skip-cart behavior.
- [x] Persist settings for direct checkout and existing quick settings toggles.
- [x] Browser-test direct checkout from single product and shop/archive AJAX add-to-cart.
- [x] Map Quick Settings to first-pass feature behavior: Direct Checkout, Guest Checkout, Urgency Timer.
- [x] Wire Order Bump quick setting to a configured-product AJAX add-to-cart engine.
- [x] Add Order Bump admin settings foundation with product, copy, placement, and targeting rules.
- [x] Polish Order Bump admin UX with product dropdown, live preview, rule summary, and lock checklist.
- [x] Browser-QA Order Bump checkout rule matching and AJAX add-to-cart with a configured required product.
- [x] Add Upsell Funnel pre/post-purchase rules UI foundation with AJAX save.
- [x] Polish Upsell Funnel admin QA states: save feedback, disabled discount input, safe-mode guidance, and live summary.
- [x] Add popup modal checkout.
- [x] Add slide-in checkout.
- [x] Add reCAPTCHA v3 module contract with safe no-op when keys are missing.
- [ ] Persist settings for popup/trust badge behavior.

### Milestone D - Analytics Foundation

- [x] Create CheckFlow local pixel event log table.
- [ ] Create `checkflow_analytics_events`.
- [ ] Track checkout view, started, payment selected, coupon applied, order placed, abandoned.
- [ ] Keep PII out of analytics metadata.
- [x] Connect admin dashboard cards/funnel/payment breakdown to WooCommerce and CheckFlow local event data.
- [x] Add Pixel Tracking CSV export, retention control, and local event log controls.
- [ ] Add analytics CSV export and date range filters after base analytics data is reliable.

### Milestone E - Pro Feature Modules

- [x] Field editor first pass with WooCommerce field filters for label, required, visibility, and priority.
- [x] Field editor drag/drop ordering and custom field add for Text, Select, Checkbox, Date, Textarea.
- [ ] Field editor with conditional logic, richer validation rules, and 20+ advanced field types.
- [ ] Template system.
- [ ] BD payment gateways and `checkflow_payment_logs`.
- [ ] Courier integrations and order meta tracking IDs.
- [x] Pixel Tracking local event log, advanced controls, and provider setup UI.
- [ ] Server-side external tracking and retry queue.
- [x] Order bump settings and lock-ready rules engine foundation.
- [ ] Upsell customer-facing execution engine and sales performance table.

### Milestone F - Hardening & Release

- [ ] PHPUnit/unit/integration tests.
- [ ] Security audit document.
- [ ] WordPress.org `readme.txt`.
- [ ] Pre-launch checklist.
- [ ] Screenshots and plugin icons.
- [ ] PHP 7.4-8.3 and WooCommerce 7-9 compatibility tests.

## 6. Pixel Tracking QA Checklist

Pixel Tracking QA is now documented in:

- `docs/PIXEL_TRACKING_QA_CHECKLIST.md`
- `docs/PIXEL_TRACKING_EXTERNAL_FIRING_PLAN.md`

This checklist should be used before starting external tracking work and again before release.

## 7. Next Action Recommendation

Current recommended next task: continue non-tracking feature work. Real-ID tracking verification and bKash/Nagad payment settings foundation are intentionally deferred until later.

Before final real-ID tracking verification:

1. Re-run `docs/PIXEL_TRACKING_QA_CHECKLIST.md`.
2. Keep CheckFlow Local Event Log as the source of truth for event IDs and payload debugging.
3. Follow `docs/PIXEL_TRACKING_EXTERNAL_FIRING_PLAN.md` for provider-specific payload contracts:
   - Meta browser Pixel first, then future CAPI.
   - Google Ads / GA4 browser conversion events first, then future enhanced conversions.
   - TikTok browser Pixel first, then future Events API.
4. Keep external tests behind admin toggles and debug mode until each provider is verified.

## 8. Deferred Task Notes

### Final Tracking QA

Real IDs should be added only near the end of the broader feature build:

- Meta Pixel ID.
- Google GA4 / Ads conversion IDs.
- TikTok Pixel ID.
- Real test order purchase verification.
- Server-side CAPI/API verification after browser tracking passes.

### Bangladesh Payment Settings Foundation

The bKash / Nagad / Rocket / SSLCOMMERZ settings foundation is queued for later. Planned scope:

- Provider cards in the Payment screen.
- Sandbox/live mode per provider.
- Credential fields saved securely in `wp_options`.
- Readiness checklist per provider.
- Safe no-op gateway foundation before real API calls.
- Real payment API tests later, after settings and gateway contracts are stable.
