Repairs and verification — 26 October 2026

The logistics dashboard now returns HTTP 200 for logistics admin and staff. Its HTTP 500 was caused by `LogisticsAlerts` querying a missing `deliveries.returned_at` column. The configured database lacked nine columns required by the rider workflows. It also rejected partial-refund payment status and the lost/damaged/returned-to-seller delivery statuses.

Two targeted migrations were applied to the configured local database. They add the missing columns and extend the existing status enums while retaining existing values, defaults, and records. They are in the normal migration directory so they are discovered on future runs. Their rollback methods retain workflow data rather than removing columns or narrowing statuses.

```powershell
php artisan migrate --path=database/migrations/2026_10_26_000001_repair_delivery_workflow_columns.php --force
php artisan migrate --path=database/migrations/2026_10_26_000002_expand_workflow_status_values.php --force
```

No application records were submitted through the browser, no credentials or `.env` values were changed, and no commit was made. The source started from the resolved merge: rider logic and the redesigned UI remain the baseline. The old `App\Models\Logistics\Delivery` model is absent, with no references remaining in application code.

**Function repairs**

- Orders now receive unique ULIDs rather than order numbers that collide within the same second.
- Sellers can read/reply only to their own conversations, including legacy unowned conversations with explicit message participation.
- Refunds lock the order and ledger, cap the remaining refundable amount, and propagate failures. Replaying a dispute resolution does not issue another ledger refund.
- Partial returns restore only the returned product/variant quantity. Completion is idempotent; cumulative returned units cannot exceed purchased units. Return requests calculate merchandise refunds after voucher discounts. Approval/rejection/receipt use locks and verify seller ownership.
- Buyer cancellation locks the order and all deliveries, rejects parcels already collected, cancels eligible shipments, closes pending pickup requests, restores stock and vouchers once, and rolls back if ledger/voucher restoration fails. Reviewed pickup decisions retain their original state, following the rider branch's rule.
- Withdrawal approval and payout creation are atomic. Requests and approval/rejection coordinate through a seller lock, and payout requests check balance/pending requests under that lock.
- Existing inactive web sessions are revoked. Guest access remains available.
- Cart API and checkout share sale-aware prices, including seller row/user ID compatibility. Missing cart update/remove/clear and buyer order-detail API routes are registered with existing authentication/activity checks and ownership scoping.
- Cart mutations use POST with CSRF tokens. The product modal, cart controls, wishlist, and legacy product view submit the corresponding forms.
- Selected checkout uses the same seller-group summary as all-items checkout, retaining effective prices, quantities, variants, and shipping per shop.
- Mobile fixes retain the existing visual design: checkout stacks, buyer header fits and its signup label is visible, product grid columns can shrink, delivery actions wrap, chart canvases stay within their containers, and payment figures wrap within cards.

**Files changed**

Paths are relative to the repository root. Existing controller/function/field names remain unless an addition is described; UI changes retain the merged main design.

| File | Change and preserved behavior |
| --- | --- |
| `app/Http/Controllers/Admin/PaymentActionController.php` | Atomic, locked withdrawal approval/rejection and idempotent dispute resolution; existing admin actions and responses remain. |
| `app/Http/Controllers/Api/CartController.php` | Sale-aware line prices; existing cart data contract remains. |
| `app/Http/Controllers/Api/OrderController.php` | Shared cancellation and cumulative, discounted returns; rider checkout, OTP routes, shipping and voucher logic remain. |
| `app/Http/Controllers/Seller/ChatController.php` | Conversation membership replaces the buyer-order authorization shortcut; explicit legacy participation remains supported. |
| `app/Http/Controllers/Seller/PayoutController.php` | Locked request balance/pending checks and currency precision validation; existing payout methods remain. |
| `app/Http/Controllers/Seller/ReturnController.php` | Seller ownership and locked return transitions; existing approve/reject/receive actions remain. |
| `app/Http/Middleware/EnsureActiveWebAccount.php` | New middleware rejects stale inactive/missing buyer accounts before web actions. |
| `app/Models/Order.php` | Unique order numbers and connection-aware schema lookup; existing total synchronization remains. |
| `app/Models/OrderStatusHistory.php` | Accepts `note`, writes canonical `notes`, reads both legacy names. |
| `app/Support/OrderCancellation.php` | New shared, transactional buyer cancellation service; pending versus reviewed pickup policy remains. |
| `app/Support/Payments.php` | Capped, locked, failure-propagating refunds; optional strict cancellation voiding; COD ledger conventions remain. |
| `app/Support/Returns.php` | Locked, idempotent completion with cumulative quantities and quantity-specific restocking. |
| `app/Support/Sales.php` | Supports both existing seller identity forms when finding running sales. |
| `app/Support/Stock.php` | Optional return quantity; callers restoring full order lines retain their default behavior. |
| `app/Support/Vouchers.php` | Optional strict restoration lets buyer cancellation roll back on failure; existing callers retain their behavior. |
| `bootstrap/app.php` | Registers the active-account web middleware before timezone handling; route/middleware configuration remains. |
| `database/migrations/2026_10_26_000001_repair_delivery_workflow_columns.php` | Adds missing return/retry, OTP, and COD fields without replacing existing rider data. |
| `database/migrations/2026_10_26_000002_expand_workflow_status_values.php` | Adds accepted enum values without removing any existing values. |
| `routes/api.php` | Adds four missing actions. Public buyer browse paths and legacy `/api/catalog` resources retain the approved split. |
| `routes/web.php` | Cart POST methods and shared cancellation; existing paths, controllers, reason names and other rider routes remain. |
| `public/css/shop.css` | Shrinkable product columns and button styling for POST forms; existing palette, typography and cards remain. |
| `resources/views/admin/payments.blade.php` | Payment figures/labels wrap in mobile cards; redesigned payment layout remains. |
| `resources/views/buyer/wishlist.blade.php` | CSRF-protected add-to-cart form with the existing button design. |
| `resources/views/cart.blade.php` | POST quantity/remove forms with existing cart controls and variant-aware rows. |
| `resources/views/checkout.blade.php` | Responsive grid and correct selected-item summary; existing forms, fields and styles remain. |
| `resources/views/layouts/website.blade.php` | Compact mobile buyer header and visible signup label; logo, links and design remain. |
| `resources/views/logistics/dashboard.blade.php` | Chart row stacks on tablets and canvases respect available width; existing charts/actions remain. |
| `resources/views/logistics/deliveries/index.blade.php` | Wrapping actions and shrinkable search field; existing redesigned filters/actions remain. |
| `resources/views/product.blade.php` | Existing `confirmOpt` submits cart POST with CSRF, quantity and variant; Buy Now navigation remains. |
| `resources/views/shop/cart.blade.php` | Legacy view delegates to the current cart, preserving the redesigned UI and shared variant-aware behavior. |
| `resources/views/shop/product.blade.php` | Legacy add-to-cart uses POST, retaining its existing button styling. |
| `tests/Feature/CommerceRegressionTest.php` | Twenty isolated regression tests; no application database migration/reset. |
| `docs/audits/2026-10-26/functional-responsiveness.md` | Original audit labeled historical; finding 6 corrected to distinguish valid repeat partial returns from excess quantities. |
| `docs/audits/2026-10-26/repairs.md` | This repair record, validation results and remaining concerns. |
| `docs/audits/2026-10-26/verification.json` | Post-repair browser/HTTP summaries and interaction evidence. |
| `docs/audits/2026-10-26/shop-mobile-fixed.png` | Post-repair 320px shop screenshot. |
| `docs/audits/2026-10-26/deliveries-mobile-fixed.png` | Post-repair 320px deliveries screenshot. |

The original `reproductions.json`, `shop-mobile.png` and `checkout-mobile.png` remain historical evidence from the initial audit.

**Validation**

- `php artisan test --filter='CommerceRegressionTest|ExampleTest'`: 22 tests passed, 105 assertions, comprising 20 regression tests and the two original example tests. Tests use SQLite in memory with small isolated fixtures. Logistics password login and its dashboard redirect passed for admin and staff.
- Regressions cover migration repeatability/data preservation, fast order creation, history notes, partial product/variant returns, cumulative return limits, voucher-adjusted refunds, replayed/capped refunds, rollback on refund/cancellation failure, multi-shipment cancellation, reviewed pickup preservation, payout failure/replay/request balance, seller conversation isolation, cart ownership and stale-session rejection.
- Read-only HTTP checks: 65 of 69 requests returned 200, including both logistics dashboards. The other four are deliberately blocked by the audit's read-only transaction: buyer address repair in profile/checkout and logistics message read-marker updates. These are harness restrictions, not reproduced application failures.
- Edge: 66 measurements across 22 contexts at 375/768/1440px; 60 rendered, with zero document overflows, captured JavaScript exceptions or failed network requests. Six measurements encountered the same profile/checkout read-only guard. Checkout arithmetic/layout was separately exercised through the actual template with synthetic seller groups: both button and summary show 230 for merchandise 130 plus two shipping fees of 50.
- Additional 320px checks passed for shop, product, deliveries, selected checkout, and admin payments. Buyer/seller/admin/logistics sidebar controls opened. The product modal generated a POST cart form with CSRF and quantity without submitting it to application data.
- Changed PHP files passed syntax checks; Blade templates compiled; `git diff --check` passed. Route registration audit found 415 registrations, no duplicate route names or method/path combinations. No unmerged files or conflict markers remain in the checked application sources.

**Uncertain or still suspicious**

- The complete MySQL feature suite is still blocked because the configured account cannot access `invoiz_ecommerce_test`. SQLite fixtures do not validate MySQL concurrency/deadlocks or replace the rider lifecycle suite. The real configured MySQL schema was inspected again to confirm the new columns/status values.
- Migration history still reports older migrations pending despite existing tables. These two additive repairs were run explicitly. Rebuilding a fresh database or running all historical migrations needs separate reconciliation; do not infer that a full migration run was validated here.
- The return API still chooses its order line using `product_id`. An order containing multiple variants of the same product can have ambiguous return selection. That needs an explicit order-item selection contract across the clients.
- Checkout ledger creation and final settlement helpers still catch/log some database failures. Refunds and buyer cancellation now propagate failures, but fault handling for those other financial paths remains a code-review concern.
- Existing mixed seller row/user IDs can be ambiguous if identifiers overlap between accounts. This repair supports the established dual-ID convention; it does not migrate all historical ownership keys.
- Real mail/Google sign-in, uploads, physical rider handoffs, external money movement and concurrent requests were not exercised end to end. Refund changes concern the application's accounting ledger; they do not add a bank refund integration.
- PHP 8.5 emits a deprecation from the installed Collision test-output dependency. It did not prevent the tests from passing.
