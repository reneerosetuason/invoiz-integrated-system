# Required mobile cancellation reason — 29 October 2026

The mobile order cancellation form now requires the buyer to choose a reason.
It previously sent `changed_mind` regardless of the buyer's reason.

The options match the buyer website's order form:

- Changed my mind
- I ordered by mistake
- I found a better price
- Delivery is taking too long
- I ordered the wrong item
- Other reason

No option is selected initially. Submitting without a choice shows a required
field error. Other reason reveals an explanation field, validated against the
existing mobile API's 10–300 character rule. The selected enum and, when
applicable, trimmed explanation are sent to the existing cancellation endpoint.
Changing from Other to another reason omits the explanation from the request.
Keeping or dismissing the order form sends no cancellation.

## Changes

- `buyer_mobile/lib/screens/order_detail_screen.dart`: native form with the
  website's reason choices, required validation and conditional details.
  The action stays busy through the form, request and order refresh to prevent
  concurrent cancellation attempts. Existing error feedback is retained.
- `buyer_mobile/test/order_cancellation_test.dart`: covers required selection,
  all five standard reason values, Other validation/trim, changing reasons,
  dismissing the form, refreshed order status and retry after server rejection.
- `buyer_mobile/test/shopping_layout_test.dart`: reviews the new dialog and
  Other details at 320px/150% text with the keyboard visible. The capture avoids
  artificially scrolling an already visible cancellation field to the top.
- `tests/Feature/BuyerMobileIntegrationTest.php`: verifies missing/unknown
  reasons and invalid Other details are rejected without changing order/stock;
  a valid explanation is stored and cancellation restores stock.

## Verification

- Full Flutter suite: **182 tests passed**. Final shortened dialog wording
  also passed its focused large-text/keyboard render check.
- Backend integration suite: **27 tests passed, 261 assertions**, using an
  isolated in-memory database.
- Analyzer: zero errors/warnings; existing 61 informational items remain.
- Reviewed initial selection, Other details and keyboard captures. Field and
  action text remain readable, including the dialog title.
- Source comparison confirms that the other order actions are unchanged.
  `git diff --check` passes.
- Android and web builds succeeded; `/buyer-app/` was refreshed. Installed the
  APK over the existing TECNO KI7 app, restored local API forwarding and
  launched INVOIZ. Its process was running with zero Flutter/fatal errors in
  the checked startup logs. App data was retained. Device validation checked
  startup only; the cancellation submissions used isolated fixtures.

The buyer website and API already enforce cancellation reasons, so neither
required a production-code change. Eligibility, stock restoration, payments,
vouchers and logistics cancellation continue using the existing server rules.
No real order was cancelled during testing. No environment file was edited,
and no commit was created.
