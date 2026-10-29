# Checkout, pickup address and variation stock — 29 October 2026

The checkout screenshot combined a valid pickup-address rejection with a false
"Your cart is empty" state. The seller had no structured pickup address saved,
and Account Management had no form for entering it. Mobile checkout assigned its
cart state only after preview succeeded, so preview failures left the cart null.

Changes:

- `buyer_mobile/lib/screens/checkout_screen.dart`: load cart items before preview;
  show a persistent checkout error with retained items, retry and back actions.
  Network failures and unavailable selections are distinct from a truly empty
  cart. Placing an order requires a valid quote.
- `app/Http/Controllers/Seller/ProfileController.php`, `routes/seller/web.php`,
  `resources/views/seller/account/index.blade.php`: authenticated Pickup Address
  form at `/seller/account#pickup-address`; requires street address, barangay,
  city and province, with optional region/postal code. Updates only the current
  seller's store. Delivery completeness checks still apply.
- As requested, saved a testing-only address for **akjshd** through the new
  controller: `123 Demo Street (testing only), Barangay 1, Manila, Metro Manila,
  NCR, 1000`. Backed up prior address fields locally. At that step, other stores
  were not edited.
  Replace this demo address with the real pickup address before actual deliveries.
- `app/Http/Controllers/Seller/ProductController.php`: validate variation
  quantities and reject totals above general product stock, including requests
  that lower general stock while retaining existing variations. Preserve IDs for
  retained variations, reject foreign/duplicate entries, and support removing all
  variations. Product updates lock the product row.
- Seller create/edit templates, their JavaScript and `_variant-config.blade.php`:
  live allocation totals and per-row maximums, clear over-allocation warning,
  disabled save while overallocated, stable variation IDs and restored form input.
- `app/Support/Stock.php`, `app/Models/Cart.php`, buyer API cart/order controllers:
  variation units are allocated within general stock. Cart and checkout enforce
  combined quantities across variations; final stock deduction locks and updates
  both product and variation atomically. Base purchases cannot consume stock
  allocated to variations. Cancellation/partial returns restore the counts used.
- `app/Models/OrderItem.php` and additive migration
  `2026_10_29_000003_track_product_stock_deductions.php`: preserve whether general
  product stock was deducted. Existing variant orders retain their previous
  variant-only restoration behavior; new orders restore both counts. Applied
  this migration locally without changing historical quantities or order totals.
- Mobile product model/detail controls and buyer website product options cap
  variation availability and quantity by general stock, including imported records
  whose variation counts are already inconsistent.
- Seller product forms explain Cost Price as the cost to source/make one item.
  It feeds profit reports; Selling Price plus the variation adjustment determines
  the buyer's item price. The current report uses the product's current cost,
  rather than a historical cost snapshot; that existing reporting behavior was
  not changed in this task.

Verification:

- **193 Flutter tests passed**. Five focused checkout tests also passed with real
  app fonts; reviewed failure renders at 320px and 360px with 150% text.
- **133 backend cases passed, 1046 assertions** across stock/pickup, buyer web and
  mobile, voucher scope/status, product chat, commerce and security. All tests
  used isolated in-memory SQLite. Existing independent-stock expectations were
  updated to the requested general-stock cap.
- Chromium at 360px and 1280px verified pickup required fields, seller allocation
  warnings and save blocking/recovery, persistent IDs, and buyer variation
  quantity limits. Zero JavaScript errors or document overflow. Browser write
  requests were blocked. No live orders, payments or carts were created/changed.
- Analyzer: zero errors/warnings, 61 informational items. PHP/JavaScript syntax,
  route registration and `git diff --check` passed. Android and embedded buyer
  web builds succeeded.
- Installed and launched the final update on the TECNO KI7 while preserving app
  data. Its own startup log contained zero Flutter/fatal errors. No live checkout
  was performed on the phone.

Existing inventory requiring seller correction:

| Product ID | Product | General stock | Allocated to variations |
| --- | --- | ---: | ---: |
| 1 | Classic White T-Shirt | 101 | 700 |
| 2 | Wireless Bluetooth Earbuds | 50 | 100 |
| 3 | Stainless Water Bottle 750ml | 80 | 240 |
| 4 | Moisturizing Facial Serum | 60 | 120 |
| 5 | Yoga Mat Non-Slip | 40 | 120 |
| 7 | Instant Coffee 100g | 200 | 400 |
| 23 | testing | 33 | 36 |
| 118 | Shirt | 10 | 90 |
| 126 | Testing | 8 | 20 |

Those existing physical-stock allocations were retained for seller review.
The new form flags them and rejects saving an invalid allocation; cart/checkout
cannot purchase more than general stock. Correct the per-variation quantities to
match actual inventory, or correct general stock if that count is inaccurate.
On the subsequent request to add demo pickup addresses for all sellers, filled
the five remaining incomplete stores (seller record IDs 4, 6, 7, 27 and 28) with
the same testing-only address above. Preserved all 22 already-complete addresses
and any nonblank fields. All 27 nondeleted seller records now pass the actual
`DeliveryServiceability::pickupAddressProblem()` check using their user IDs.
Verified that no unrelated seller fields changed. Demo addresses must be replaced
through Account Management > Pickup Address before actual deliveries.

The address-only follow-up backup and verification result are stored in
`%TEMP%/invoiz-all-seller-demo-pickups-20261029`. No app rebuild was required.

Local logs, fixture renders, screenshots, address backup and inventory review:
`%TEMP%/invoiz-checkout-stock-20261029`. Existing unrelated changes are preserved;
no commit was made.
