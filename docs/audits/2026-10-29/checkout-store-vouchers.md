# Store-scoped checkout vouchers — 29 October 2026

Checkout now shows only vouchers that apply to the stores and items actually
selected for purchase. A voucher's minimum spend and percentage discount use
its own store's merchandise subtotal. Other stores' cart items cannot qualify
the voucher or increase its savings. Eligible platform offers remain available.

Changes:

- `app/Support/Vouchers.php`: shared voucher-owner resolution (voucher ownership
  uses `sellers.id`, cart/order groups use `users.id`), eligible checkout offers,
  server-calculated savings, and capped persisted amounts for stacked discounts.
  Offers already used by the buyer, inactive/expired/exhausted offers and offers
  below their minimum spend are excluded from checkout.
- `app/Support/Shipping.php`: uses the same owner resolution for validation.
- `app/Http/Controllers/Buyer/Api/OrderController.php`: preview returns eligible
  offers based on the authenticated buyer's selected, server-priced cart lines.
  Preview and transactional final checkout continue to reject unrelated store
  vouchers, including manually submitted claim IDs.
- `app/Http/Controllers/Buyer/Api/VoucherController.php`: the compatibility code
  validator now derives scope and prices from the buyer's cart instead of trusting
  the supplied subtotal; foreign/missing selected cart items are rejected.
- `app/Models/Order.php`: store merchandise and shipping discounts stay with the
  issuing store in delivery collection, refunds and seller calculations.
  Platform merchandise discounts share the remaining merchandise and conserve cents.
- `routes/buyer/web.php`, `resources/views/buyer/checkout.blade.php`: filter claimed
  offers for single-product, store, whole-cart and selected-item checkout. Validate
  store scope before cart/stock changes; attach store shipping vouchers to their
  own store even when another store is first in the cart.
- `buyer_mobile/lib/screens/checkout_screen.dart`, `buyer_mobile/lib/models/voucher.dart`:
  use the preview's eligible offers and savings rather than the global voucher list
  or whole-cart percentage estimates. Refresh offers after apply/remove, explain an
  empty applicable list, and show shipping savings on an applied shipping voucher.
- `tests/Feature/CheckoutVoucherScopeTest.php`: store filtering, minimum spend,
  forged foreign-store claims, ID collisions, merchandise/shipping allocation,
  stacked discount caps, all website checkout modes, and compatibility validation.
- `buyer_mobile/test/checkout_voucher_scope_test.dart`: scoped offer loading,
  selected cart IDs, correct savings suggestions, apply/remove, shipping savings,
  empty state and stale-offer server rejection. No checkout requests are sent.

Verification:

- Backend regressions: **63 tests passed, 494 assertions**, using isolated in-memory
  SQLite fixtures. No live orders, payments, carts or voucher claims were changed.
- Existing buyer website shopping suite: **22 tests passed, 132 assertions**.
- Full Flutter suite: **188 tests passed**; includes phone layouts and keyboard/large
  text checks. Analyzer: zero errors or warnings, 60 informational items.
- PHP syntax and `git diff --check` passed. No database migration is required.
- Android debug APK and embedded buyer web app builds succeeded.
- Updated and launched on the paired TECNO KI7 with existing app data preserved.
  The updated app was running with zero Flutter/fatal errors in its own startup
  log. No live checkout was performed on the phone.

Existing behavior retained: the website creates separate orders per store, and
platform vouchers apply to the first order. The mobile API creates one combined
order, with platform vouchers applying across that order. Store vouchers in both
flows apply only to their issuer. General voucher browsing/collection stays available.

Historical order and delivery records have not been rewritten. Orders created
before this fix can retain their previously stored delivery collection amounts;
the corrected per-store calculation is used for new checkouts. Existing staged
and unrelated working-tree changes are preserved; no commit was made.
