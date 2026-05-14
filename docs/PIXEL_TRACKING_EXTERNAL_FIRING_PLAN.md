# Pixel Tracking Real External Firing Plan

Last update: 2026-05-14

## Goal

Implement real external tracking in a controlled, testable way without breaking checkout, slowing storefront pages, or firing duplicate purchase events.

Real provider verification with real IDs is deferred until the end of the broader feature build. Browser firing code can exist now, but live verification should wait until the remaining major modules are stable.

External providers:

- Meta Pixel browser events first, then Meta CAPI.
- Google Ads / GA4 browser conversion events first, then enhanced conversions.
- TikTok Pixel browser events first, then Events API.

CheckFlow Local Event Log remains the source of truth for event IDs, payload previews, and debugging.

## Non-Negotiable Rules

- Never hijack WooCommerce order/payment submission.
- Never block checkout if a provider request fails.
- Never send PII unless the specific provider setting and privacy rules are ready.
- Always generate and reuse a stable `event_id`.
- Purchase must have duplicate guard on order received refresh.
- External firing must be behind provider enable toggles.
- Debug/test mode must be available before live verification.
- Local Event Log must record what would be sent externally.

## Current Foundation

Already implemented:

- Local Event Log table.
- Browser events:
  - `PageView`
  - `ViewContent`
  - `AddToCart`
  - `InitiateCheckout`
  - `Purchase`
- Event ON/OFF controls.
- Provider setup UI and readiness checklist.
- Test event, export CSV, clear logs, retention.
- Meta browser Pixel loader foundation.

Implemented in Phase 1:

- Meta browser firing.
- Google browser firing.
- TikTok browser firing.

Not implemented yet:

- Server-side Meta CAPI.
- Google Enhanced Conversions.
- TikTok Events API.
- Retry queue.

## Phase 1 - Browser Events Only

Purpose: verify real provider pixels with minimal risk.

### Meta Pixel

Use current `fbq` foundation.

Events:

- `PageView` -> `fbq('track', 'PageView')`
- `ViewContent` -> `fbq('track', 'ViewContent', params, { eventID })`
- `AddToCart` -> `fbq('track', 'AddToCart', params, { eventID })`
- `InitiateCheckout` -> `fbq('track', 'InitiateCheckout', params, { eventID })`
- `Purchase` -> `fbq('track', 'Purchase', params, { eventID })`

Required:

- Meta enabled.
- Meta Pixel ID present.
- Event enabled in CheckFlow advanced settings.

Debug:

- Console: `[CheckFlow Pixel] Meta fired`
- Local log provider state stores Meta enabled/configured.

### Google Ads / GA4

Add `gtag.js` browser foundation only.

Supported setup:

- GA4 Measurement ID: `G-XXXX`
- Google Ads Conversion ID: `AW-XXXX`
- Conversion label for purchase conversion.

Events:

- `PageView`:
  - GA4 config/page_view when `G-` ID exists.
- `ViewContent`:
  - GA4 event `view_item`.
- `AddToCart`:
  - GA4 event `add_to_cart`.
- `InitiateCheckout`:
  - GA4 event `begin_checkout`.
- `Purchase`:
  - GA4 event `purchase`.
  - Google Ads conversion event when `AW-` ID and conversion label exist.

Required:

- Google enabled.
- Measurement/Conversion ID present.
- Purchase conversion label present for Ads purchase conversion.

Debug:

- Console: `[CheckFlow Pixel] Google fired`
- Do not send enhanced conversion customer data in this phase.

### TikTok Pixel

Add TikTok browser pixel foundation only.

Events:

- `PageView` -> `ttq.page()`
- `ViewContent` -> `ttq.track('ViewContent', params)`
- `AddToCart` -> `ttq.track('AddToCart', params)`
- `InitiateCheckout` -> `ttq.track('InitiateCheckout', params)`
- `Purchase` -> `ttq.track('CompletePayment', params)`

Required:

- TikTok enabled.
- TikTok Pixel ID present.

Debug:

- Console: `[CheckFlow Pixel] TikTok fired`
- API token ignored in browser-only phase.

## Phase 2 - Payload Preview Panel

Before server-side firing, add an admin-only payload preview:

- Select provider.
- Select event.
- Show sample browser payload.
- Show sample server payload.
- Show missing field warnings.
- Show which values come from WooCommerce cart/order.

This prevents guessing during live provider setup.

## Phase 3 - Server-Side Events

Purpose: improve reliability after browser firing is verified.

### Meta CAPI

Endpoint:

- `https://graph.facebook.com/{version}/{pixel_id}/events`

Required settings:

- Pixel ID.
- Access token.
- Test event code for sandbox testing.

Payload:

- `event_name`
- `event_time`
- `event_id`
- `action_source: website`
- `event_source_url`
- `custom_data`
- `user_data` only after privacy-ready hashing pass.

Deduplication:

- Use same `event_id` as browser event.

### Google Enhanced Conversions

Do not implement until:

- Consent/privacy copy is ready.
- Hashing rules are documented.
- Purchase order email/phone handling is audited.

Start with browser Google Ads conversion first.

### TikTok Events API

Endpoint:

- TikTok Events API endpoint by current documentation.

Required settings:

- Pixel ID.
- API token.

Deduplication:

- Use CheckFlow `event_id`.

## Phase 4 - Retry Queue

Add a provider delivery table only after server-side events exist.

Suggested table:

- `id`
- `event_log_id`
- `provider`
- `event_name`
- `event_id`
- `payload`
- `status`
- `attempts`
- `last_error`
- `next_retry_at`
- `created_at`
- `updated_at`

Statuses:

- `pending`
- `sent`
- `failed`
- `skipped`

Retry rules:

- Never retry browser events.
- Retry server events only.
- Max 5 attempts.
- Exponential backoff.

## Privacy And Consent

Before sending customer/user data:

- Add provider-specific consent settings.
- Add clear admin warnings.
- Hash PII server-side only.
- Never store raw provider access tokens in frontend JS.
- Never expose server API tokens to the browser.

For now:

- Browser-only phase sends product/cart/order commercial metadata, not enhanced PII.

## Event Payload Contract

### Shared Fields

- `event_name`
- `event_id`
- `page_url`
- `currency`
- `value`
- `content_ids`
- `content_type`
- `num_items`
- `order_id` for Purchase only

### Local Event Log Context

Local log should include enough context to reproduce provider payload:

- Page URL.
- Product IDs.
- Cart item count.
- Currency.
- Value.
- Order ID for purchase.
- Provider enabled/configured state.

## Browser QA Script

Run after Phase 1 implementation:

1. Enable Debug console log.
2. Enable one provider at a time.
3. Save provider settings.
4. Visit shop page.
5. Confirm PageView fires.
6. Visit product page.
7. Confirm ViewContent fires.
8. Add product to cart.
9. Confirm AddToCart fires.
10. Visit checkout.
11. Confirm InitiateCheckout fires.
12. Place test order.
13. Confirm Purchase fires once.
14. Refresh order received page.
15. Confirm duplicate Purchase does not fire.

## Provider Verification Tools

Meta:

- Meta Pixel Helper.
- Events Manager Test Events.

Google:

- Tag Assistant.
- GA4 DebugView.
- Google Ads conversion diagnostics.

TikTok:

- TikTok Pixel Helper.
- TikTok Events Manager test tools.

## Implementation Order

1. [x] Add provider-specific browser firing in `public/js/checkflow-pixel.js`.
2. [x] Keep local event logging first in the `fire()` flow.
3. [x] Add provider-specific debug console messages.
4. Add admin payload preview panel.
5. Browser-test Meta.
6. Browser-test Google.
7. Browser-test TikTok.
8. Only after browser firing is verified, start server-side CAPI/API work.

## Done Criteria For Phase 1

- Meta browser events fire only when Meta is enabled and Pixel ID exists.
- Google browser events fire only when Google is enabled and IDs exist.
- TikTok browser events fire only when TikTok is enabled and Pixel ID exists.
- Disabled events do not fire externally.
- Local log still records enabled events.
- Purchase refresh does not duplicate.
- No checkout blocking if external script fails.
- No provider API tokens appear in frontend source.
