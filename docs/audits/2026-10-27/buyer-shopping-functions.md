# Buyer shopping fixes — 27 October 2026

Applied to C:/Invoiz. This task fixes the existing buyer shopping flow, without changing the database schema, installing dependencies, committing or pushing. Existing logistics changes were preserved.

## Fixed behavior

- Product pages retain **Add to Cart** after an item has already been added, while keeping **View Cart** available. Users can add more units or another variation.
- Product options select an available variation, reset quantity/variation/stock correctly when reopened, cap quantities at stock and 200, update unit/total prices, show sold-out feedback and prevent duplicate Add to Cart submits. Escape closes the popup and returns focus; returning from browser back-cache restores its controls.
- An available variation can be purchased even when the separate base-product stock is zero. Foreign, inactive and deleted variations cannot silently become base-product purchases. Inactive products cannot be added.
- Cart additions and increments serialize through the cart header, enforce the 200-unit limit, and account for the quantity actually added after a stock cap.
- Decrease and Remove use the current buyer's cart-item ID exclusively. Product IDs are no longer ambiguous fallbacks.
- Checkout selection uses cart-item IDs exclusively, so a product-ID collision cannot include unchecked rows. Only the selected purchased rows are removed afterward.
- Buy Now preserves the chosen quantity, variation and price through the preview and final order.
- Checkout posts the product/quantity or cart-item IDs displayed on that page. Another checkout tab changing the session cannot replace them. Seller checkout also prefers its submitted seller ID. Server-side stock, active-product, voucher, address and ownership checks still apply.
- Cart/product/checkout/header views consistently recognize an authenticated login even without the legacy buyer snapshot, fixing a cart that could appear empty after a successful addition.
- Shop and Sale cart summaries use the same sale-aware variation prices as the cart.
- Selecting individual cart items keeps the seller checkbox's checked/indeterminate state and totals synchronized.
- Large cart groups reveal when they enter the viewport; the old 12% intersection threshold could keep a tall group invisible.

## Files

| File | Change |
| --- | --- |
| routes/web.php | Buyer cart actions, exact selection, Buy Now preview and checkout submission handling |
| app/Models/Cart.php | Shared availability/variation checks, atomic cart limits and exact selected-line helper |
| app/Support/BuyerSession.php | New small buyer view-context helper supporting normal auth and legacy sessions |
| app/Http/Controllers/Web/HomeController.php | Consistent shop/sale cart preview totals |
| resources/views/product.blade.php | Repeat-add button, option state, submit protection, keyboard handling and validation feedback |
| resources/views/cart.blade.php | Consistent buyer context, availability, validation feedback and shared checkbox behavior |
| resources/views/checkout.blade.php | Consistent buyer context and hidden IDs matching its preview |
| resources/views/layouts/website.blade.php | Consistent buyer header and cache version for the updated shared script |
| public/frontend/js/app.js | Checkbox/total synchronization and selected checkout URL handling |
| public/js/shop.js | Fix visibility of tall cart groups |
| tests/Feature/BuyerWebShoppingTest.php | 22 isolated web-shopping regressions |

## Verification

- Existing CommerceRegressionTest baseline: **20 tests, 103 assertions passed**.
- New baseline reproduced **12 buyer web failures** after fixture schema setup; repeat basic addition and wishlist/search were already working.
- Final BuyerWebShoppingTest + CommerceRegressionTest: **42 tests, 235 assertions passed**.
- Tests include complete cash-on-delivery order placement, exact selected-line purchase/removal, selected variation stock deduction, payment/ledger creation, voucher claim/use, malformed inputs, foreign cart/address rejection, and checkout tab isolation.
- **38 Chromium checks passed**, with no JavaScript exceptions, using seven actual rendered Blade pages populated with synthetic records. Product options were checked at desktop and phone widths; cart layouts also at 390 and 320px. Browser checks verify native form methods/CSRF, selected payloads, prices, reset behavior, sold-out controls, checkbox state, popup focus, back-cache recovery and a 100-item cart group.
- PHP and JavaScript syntax checks and git diff whitespace checks passed.
- All database-backed verification uses **in-memory SQLite and fake email**. Browser cart/checkout mutations are intercepted. No real customer carts or orders were created or modified for testing. Public product browsing may increment its existing view counter.
- Source file hashes were checked against the tested staging copies. No broad database-backed logistics suite was run; it is outside this buyer task.

Artifacts: C:/HeatGuard/logs/buyer-functions/ contains the original backups, baseline/final JUnit reports, browser-results.json, synthetic page generator and screenshots. They are ignored local verification artifacts, not application dependencies. The temporary preview server is stopped after verification.
