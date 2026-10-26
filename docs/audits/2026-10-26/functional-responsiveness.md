Functional and responsiveness audit — 26 October 2026

**Historical findings before repairs.** See [repairs and current verification](repairs.md) for the subsequent fixes. The evidence below records the original application state.

The application could not at that stage be certified as fully functional or fully responsive. This audit found confirmed privacy, order-processing, refund, inventory, pricing, database-schema, and mobile-layout defects. Findings below distinguish isolated reproductions, read-only application observations, and code-review concerns.

Application source, `.env`, application database rows, and Git history were not changed. This directory contains the audit report and evidence only.

**What was checked**

- All 239 PHP files under `app/` and `routes/` passed syntax checks. Blade templates compiled successfully. Six application JavaScript files passed syntax checks.
- The two existing `ExampleTest` tests passed, with two assertions. These are basic smoke tests, not business-flow coverage.
- 69 GET page requests covered guest, buyer, seller, marketplace admin, logistics admin, and logistics staff contexts. Authentication/session state was supplied by a temporary audit harness using existing accounts. 63 returned HTTP 200. Six returned HTTP 500: two dashboard failures caused by missing columns, and four requests deliberately blocked by the read-only database guard.
- Edge checked 22 page contexts, including two synthetic checkout scenarios, at 375px, 768px, and 1440px: 66 measurements. 57 rendered successfully; six measurements encountered deliberate read-only restrictions and three encountered the actual dashboard error. Six successfully rendered mobile measurements overflowed horizontally. No JavaScript exceptions or failed network requests were captured during those sampled browser runs.
- Mobile sidebar controls opened successfully in buyer, seller, admin, and logistics layouts.
- Eleven groups of isolated business-logic reproductions ran against SQLite in memory. Their outputs are saved in [reproductions.json](reproductions.json). They use synthetic records and do not transact against the configured application database.
- Route inspection confirmed four buyer controller methods have no registered routes. The configured application schema was inspected without changing it.

**Confirmed findings, ordered by impact**

1. **High — Sellers can access another store's private conversations.**

   `Seller/ChatController.php:15`, `:37`, and `:74` authorize either the conversation's seller or any seller who has an order from that buyer. Buying from seller A therefore makes the buyer's conversation with seller B visible to A. The same predicate appears in the reply action.

   Reproduction: synthetic buyer 201 bought from seller 101 and had a private conversation owned by seller 102. Seller 101's inbox returned that conversation. This is an authorization defect, not a layout issue.

   Fix direction: authorize membership in the specific conversation. Handle legacy thread migration explicitly rather than granting access to every conversation belonging to a customer.

2. **High — Orders created within one second can collide and fail checkout.**

   `app/Models/Order.php:46` generates `order_number` with `INV-` plus a timestamp precise only to one second. The configured database has a unique index on that column. `routes/web.php:492` creates separate orders for seller groups in a loop.

   Reproduction: two immediate order creations against an isolated schema with that unique index produced a unique-constraint failure on the second order. A checkout containing multiple shops can hit this without concurrent users; separate buyers can also collide.

   Fix direction: use a concurrency-safe unique identifier and retain the database constraint. Test a multi-shop checkout and simultaneous order creation.

3. **High — The current database is missing delivery fields required by rider/logistics code.**

   Both logistics admin and logistics staff dashboard requests returned HTTP 500. The failing query is in `app/Support/LogisticsAlerts.php:103`, which references `returned_at`.

   Schema inspection found these model fields absent from `deliveries`:

   - `returned_at`, `return_reason`, `reattempt_requested_at`
   - `cod_amount_received`, `cod_received_at`
   - `delivery_otp`, `delivery_otp_expires_at`, `delivery_otp_attempts`, `delivery_otp_consumed_at`

   The missing return timestamp is a confirmed dashboard failure. Rider return, retry, OTP, and COD completion paths also reference the absent fields and need verification after schema alignment. The return/retry migration exists at `database/migrations/logistics/2026_10_07_000002_add_reattempt_requested_at_to_deliveries_table.php`; normal migration registration does not load that nested directory. Migration status also reports base migrations pending despite an existing 86-table database.

   Fix direction: reconcile the migration history and existing schema, then apply the appropriate additive migrations, including the nested logistics migration. No migrations were executed during this audit.

4. **High — Refund accounting accepts duplicate resolutions and refunds above the order total.**

   `Admin/PaymentActionController.php:65` does not reject an already-resolved dispute. `app/Support/Payments.php:210` adds the requested refund to the previous refunded amount without capping it at the remaining refundable balance.

   Reproduction: a 500-unit order with an initial 100-unit partial refund was resolved twice for another 500 units each. The recorded refunded amount became 1,100. This demonstrates an incorrect ledger; it does not demonstrate an external bank transfer.

   Fix direction: make resolution idempotent, lock the dispute/payment records, and enforce the remaining refundable amount inside the transaction.

5. **High — A partial return restores the entire purchased quantity.**

   `app/Support/Returns.php:21` calls `Stock::restore()` with the original order item. `app/Support/Stock.php:125` restores `order_items.quantity`, ignoring `return_requests.quantity`.

   Reproduction: an order line purchased five units; the buyer returned one. Stock rose from 10 to 15 instead of 11.

   Fix direction: restore the accepted return quantity to the correct product/variant and make restoration idempotent for that return.

6. **High — The same order line can be returned again after it was refunded.**

   `Api/OrderController.php:363` blocks only open return statuses. It does not subtract quantities already returned/refunded from the purchased quantity.

   The initial reproduction showed a second partial request returning HTTP 201. That alone is valid when purchased units remain, so it did not prove excessive returns. Code review found the missing cumulative check; the repair regression now verifies that remaining units are accepted and excess units are rejected.

   Fix direction: validate cumulative returned quantities under a lock; account for completed returns as well as open ones.

7. **High — A failed refund can still be reported as successfully refunded.**

   `app/Support/Payments.php:193` catches refund failures and returns no success/failure result. `app/Support/Returns.php:31` then marks the return `refunded`. The dispute action similarly marks a dispute resolved after calling the helper.

   Reproduction: with the ledger table deliberately unavailable in the isolated database, return completion still marked the return `refunded`. Stock had also been restored.

   Fix direction: propagate payment failures and atomically coordinate the refund record, stock restoration, and return/dispute state.

8. **High — Buyer cancellation leaves its shipment and pickup request active.**

   `Api/OrderController.php:260` cancels the order, restores stock, and voids the payment but does not cancel its deliveries. The web cancellation closure at `routes/web.php:632` has the same omission.

   Reproduction through the API controller: order status became `cancelled`, while its delivery remained `waiting_for_rider` and its pickup request remained `pending`.

   Fix direction: cancel eligible deliveries and close actionable pickup requests in the same locked transaction as the order cancellation. Cover multi-delivery orders and dispatched parcels explicitly.

9. **High — Existing suspended buyer sessions can still access unprotected web flows.**

   Web cart/order/checkout closures check session data or `Auth::check()` but do not consistently apply the buyer/activity middleware. `Admin/UserManagementController.php:28` changes account status without revoking sessions.

   Read-only reproduction: an authenticated buyer object with `account_status=suspended`, `status=suspended`, and an existing buyer session still received HTTP 200 from `/cart` and `/orders`. Checkout POST also lacks an activity check by inspection; no order was submitted to the application database during this audit.

   Fix direction: apply active-account and buyer-entitlement checks to all buyer web actions, including cart mutation, checkout, cancellation, and messaging; invalidate obsolete sessions when account access changes.

10. **Medium — Store sales may not apply because seller identities differ.**

    `Seller/SaleController.php:52` stores a sale using the seller-row ID. `app/Support/Sales.php:24` looks up a sale using `products.seller_id`, which may be the seller's user ID.

    Reproduction: seller row 5 belonged to user 101; its product used seller ID 101. A configured 20% sale was ignored and the price stayed 100 instead of 80. Using a matching identity made the discount apply.

    Fix direction: normalize seller identity consistently when writing and finding sales, including existing records.

11. **Medium — API cart prices disagree with checkout during a sale.**

    `Api/CartController.php:27` uses the product's base price, whereas `Api/OrderController.php:84` and `Cart::unitFor()` apply the current sale.

    Reproduction with a correctly linked sale: the API cart displayed a unit price of 100 and checkout calculated 80.

    Fix direction: use the same effective-price calculation across product display, cart, summaries, and order creation.

12. **Medium — Selected-item checkout displays inconsistent totals.**

    `resources/views/checkout.blade.php:105` uses the grouped summary only for `checkoutMode === 'all'`. Selected-item checkout falls into a one-shop summary at `:167`; that fallback also uses base product prices and product-level quantity lookup instead of the selected variant-aware lines.

    Reproduction using the actual template and two synthetic seller groups: merchandise totals 130, shipping should total 100, and the button correctly displayed 230. The summary displayed 180 with “Shipping (1 shop)”. Its individual lines also showed undiscounted product prices.

    Fix direction: render both `all` and `selected` using the same server-calculated seller groups, line quantities, effective prices, and shipping totals.

13. **Medium — Several mobile layouts overflow or clip controls.**

    Successfully rendered browser measurements at a 375px viewport:

    | Context | Document width | Observed problem |
    | --- | ---: | --- |
    | Guest shop and product | 451px | Header signup control clips off-screen. |
    | Selected checkout fixture | 395px | The order summary clips; layout remains two columns. |
    | All-items checkout fixture | 428px | The order summary clips; layout remains two columns. |
    | Logistics deliveries | 458px | Header action buttons do not fit the available width. |
    | Admin payments | 378px | Small horizontal overflow. |

    The checkout grid is fixed inline at `resources/views/checkout.blade.php:15` without a mobile column change. Buyer header styles are in `resources/views/layouts/website.blade.php:20`; delivery action buttons are in `resources/views/logistics/deliveries/index.blade.php:28`.

    No document overflow was measured at 768px or 1440px in the successfully rendered sample contexts. This does not certify unvisited pages, dialogs, longer content, or all device widths.

    Evidence: [guest shop screenshot](shop-mobile.png), [selected checkout screenshot](checkout-mobile.png).

14. **Medium — Four implemented buyer API functions cannot be reached through routing.**

    The route collection contains no actions for:

    - `Api/CartController::update()` (`:96`)
    - `Api/CartController::destroy()` (`:120`)
    - `Api/CartController::clear()` (`:130`)
    - `Api/OrderController::show()` (`:254`)

    `routes/api.php:37` exposes cart list/add but does not register those cart actions; order listing is registered but the buyer order-detail method is not.

    Fix direction: register the intended authenticated endpoints, retain ownership/activity checks, and verify the mobile client's expected paths and methods.

15. **Medium — Withdrawal approval is not atomic.**

    `Admin/PaymentActionController.php:27` marks the withdrawal approved before creating its payout, without a surrounding transaction.

    Reproduction: payout insertion was deliberately made to fail in the isolated database. The withdrawal stayed `approved` despite there being no created payout.

    Fix direction: lock and update the withdrawal and create its uniquely linked payout in one transaction. The request/approval code also has check-then-write concurrency risks; duplicate concurrent withdrawals/approvals were not reproduced in this run and remain a separate review concern.

16. **Medium — Order history notes are silently discarded.**

    Order creation/cancellation/status controllers pass `note`, but `app/Models/OrderStatusHistory.php:16` allows `notes`, not `note`, for mass assignment.

    Reproduction: cancellation created the history row, but both its `note` and `notes` fields were null instead of containing the cancellation explanation.

    Fix direction: align the model, schema, writers, and readers on one field and preserve existing history data.

**Additional code-review concerns**

17. **Web cancellation does not release used vouchers.** The web cancellation closure in `routes/web.php:632` omits `Vouchers::restoreForOrder()`, although API cancellation calls it at `Api/OrderController.php:292`. A voucher consumed by a web checkout can therefore remain unavailable after cancellation. This omission was confirmed by inspection; a separate web voucher lifecycle reproduction was not run.

18. **Cart mutations use GET routes.** `routes/web.php:191`, `:232`, `:249`, and `:259` add, increment, decrement, and remove cart items through GET. Those requests do not receive the usual CSRF verification and can be triggered by navigation or link prefetching. This was confirmed by route inspection; no cross-site mutation test was performed against application data.

**Verification limits and remaining work**

- The MySQL feature suite remains blocked: the configured user cannot access `invoiz_ecommerce_test`. The attempted buyer security test stopped with an access-denied error before its first assertion. The two passing example tests do not replace that suite.
- The temporary HTTP server rejected non-GET requests and used a MySQL read-only transaction. `/profile`, `/checkout`, and existing logistics-message selection require address-repair locks/updates or read-marker updates, so those HTTP 500s are audit restrictions, not evidence that those pages normally fail. Checkout layout/arithmetic was tested separately through the actual Blade template with synthetic inputs.
- Password login, email/Google verification, outgoing mail, actual file uploads, actual payouts/refunds, full rider delivery completion, and concurrent requests were not exercised end to end. No real orders, payments, messages, or account statuses were changed.
- Resolve authorization and financial/data-consistency findings first, align the schema, restore an isolated working test database, then rerun the complete regression suite and browser form/dialog checks. Mobile overflow and summary arithmetic also need regression coverage.
