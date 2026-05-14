# Pixel Tracking QA Checklist

Last update: 2026-05-14

## Scope

This checklist covers the current Pixel Tracking foundation:

- CheckFlow Local Event Log
- Provider setup UI for CheckFlow Local, Meta Pixel, Google Ads / GA4, and TikTok Events
- Event controls, retention, export, clear log, test event
- Interactive local tracking insights

External real provider validation with real IDs is intentionally deferred until the end of the broader feature build. Meta CAPI, Google Enhanced Conversions, and TikTok Events API will get a separate real external testing pass after the core plugin features are stable.

## Admin UI QA

- [ ] Open `CheckFlow > Pixel Tracking`.
- [ ] Confirm the page has no horizontal overflow in light mode.
- [ ] Confirm the page has no horizontal overflow in dark mode.
- [ ] Confirm provider cards are collapsed by default except CheckFlow Local.
- [ ] Open and close each provider card:
  - [ ] CheckFlow Local
  - [ ] Meta Pixel
  - [ ] Google Ads / GA4
  - [ ] TikTok Events
- [ ] Confirm each provider badge is understandable:
  - [ ] `Ready`
  - [ ] `Disabled`
  - [ ] `Needs Pixel ID`
  - [ ] `Needs IDs`
  - [ ] `Needs ID/token`
  - [ ] `Saved`
- [ ] Confirm readiness chips do not contradict the badge.
- [ ] Confirm missing chips use softer warning styling, not a heavy error state.
- [ ] Confirm helper text is short and readable inside each provider card.
- [ ] Save provider settings and refresh the page.
- [ ] Confirm saved values persist after refresh.

## Advanced Settings QA

- [ ] Open `Advanced tracking settings`.
- [ ] Toggle each event ON/OFF and save:
  - [ ] `PageView`
  - [ ] `ViewContent`
  - [ ] `AddToCart`
  - [ ] `InitiateCheckout`
  - [ ] `Purchase`
- [ ] Set retention days to a valid value between `1` and `365`.
- [ ] Try an invalid retention value and confirm it is normalized on save.
- [ ] Send a test event from the admin UI.
- [ ] Confirm the test event appears in CheckFlow Event Log after refresh.
- [ ] Export CSV and confirm a file downloads.
- [ ] Open exported CSV and confirm columns exist:
  - `event_name`
  - `event_id`
  - `page_url`
  - `context`
  - `provider_state`
  - `created_at`
- [ ] Click `Clear expired` and confirm no error is shown.
- [ ] Click `Clear all logs` and confirm the browser asks for confirmation.
- [ ] Cancel `Clear all logs` and confirm logs remain.
- [ ] Confirm `Clear all logs` only runs after confirmation.

## Local Event Logging QA

- [ ] Visit the shop page.
- [ ] Confirm `PageView` is logged when `PageView` is enabled.
- [ ] Disable `PageView`, save, visit shop again, and confirm no new `PageView` is logged.
- [ ] Visit a product page.
- [ ] Confirm `ViewContent` is logged when enabled.
- [ ] Add a product to cart from shop/archive.
- [ ] Confirm `AddToCart` is logged when enabled.
- [ ] Visit checkout.
- [ ] Confirm `InitiateCheckout` is logged when enabled.
- [ ] Place a test order.
- [ ] Confirm `Purchase` is logged on order received page.
- [ ] Refresh the order received page.
- [ ] Confirm duplicate `Purchase` is not logged for the same event ID/order.

## Interactive Insights QA

- [ ] Click `Show chart`.
- [ ] Confirm KPI cards show totals.
- [ ] Hover event chart rows and confirm tooltip appears.
- [ ] Click a chart row and confirm the Event Log filters.
- [ ] Click `All events` and confirm all recent rows return.
- [ ] Click an Event Log row and confirm details/context appear.
- [ ] Confirm long event IDs/URLs do not break the layout.
- [ ] Confirm mobile view stacks chart and rows cleanly.

## Console And Network QA

- [ ] Open browser dev tools on storefront pages.
- [ ] Confirm there are no CheckFlow JavaScript errors.
- [ ] With Debug console log ON, confirm `[CheckFlow Pixel]` logs appear.
- [ ] With Debug console log OFF, confirm debug logs stop.
- [ ] Confirm `admin-ajax.php` requests for `checkflow_log_pixel_event` return success.
- [ ] Confirm disabled events return a safe skipped response and do not create log rows.

## Current Known Non-Blocking Issues

- Third-party `sp-lazy.js` 404 can appear from another plugin/theme path and is not caused by CheckFlow.
- External provider browser firing code exists, but real-ID verification is deferred until the final tracking QA pass.
- Meta CAPI, Google Enhanced Conversions, and TikTok Events API are not part of the current browser-only phase.

## Deferred Final Tracking QA

Do this near the end of the project, after other major features are stable:

- [ ] Add real Meta Pixel ID and verify with Meta Pixel Helper / Events Manager.
- [ ] Add real Google GA4 or Ads conversion IDs and verify with Tag Assistant / DebugView.
- [ ] Add real TikTok Pixel ID and verify with TikTok Pixel Helper / Events Manager.
- [ ] Place a real test order and verify Purchase/CompletePayment fires once.
- [ ] Confirm no duplicate purchase on order received refresh.
- [ ] Only after browser tracking passes, begin server-side CAPI/API verification.

## Pass Criteria

Pixel Tracking foundation is considered passed when:

- Admin UI is readable in light/dark mode.
- Provider settings save and persist.
- Local events log correctly.
- Event ON/OFF controls work.
- Purchase duplicate guard works.
- Export, clear, and test event actions work.
- Interactive insights filter and details work without layout overflow.
