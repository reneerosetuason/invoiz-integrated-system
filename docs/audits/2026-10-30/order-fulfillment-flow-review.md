The ordering and delivery modules exist, but the current application does not consistently enforce the complete seller -> pickup rider -> logistics center -> delivery rider -> buyer flow. This review reproduced nine integration gaps without changing application behavior or production records.

Current intended sequence
-------------------------

1. The buyer checks out through the website or mobile API. Both currently accept Cash on Delivery. Checkout validates address completeness and stock, creates order items, a pending payment/ledger, and a tracking-numbered delivery. The website creates one order per seller; the mobile API attempts one shared order with one delivery per seller.
2. The seller uses Accept -> Start Preparing -> Mark as Packed. The seller can print the waybill and record a scheduled courier pickup. `CourierPickup` is a scheduling record; creating it does not itself create/review a logistics `PickupRequest` or assign a rider.
3. The logistics administrator manually routes a centerless pickup to a handling center. Center staff review the pickup; approval and rider assignment are separate actions. Assignment checks rider approval, online/active status, vehicle verification/type, license, capacity, center and service area.
4. The assigned rider accepts, heads to the shop, confirms arrival and pickup, and uses Deliver to Sorting Center. A seller pickup PIN is enforced only when the delivery actually has one. The center-handoff endpoint records the pickup rider and handoff time.
5. Center staff receive -> scan -> sort the parcel, selecting its destination center and service area. Web Dispatch separately marks the parcel dispatched. These parcel stages are distinct from `Order.status` and `Delivery.status`.
6. The intended delivery rider collects the sorted parcel through the mobile app, goes out for delivery, arrives at the buyer and completes the delivery. The normal buyer-linked rider completion path requires the buyer's delivery OTP and a recorded COD amount. It writes the delivery result, proof, transaction, remittance and final rider earning within a database transaction.
7. The cash handoff is manual/off-system: the rider marks a transfer sent and the seller confirms receipt. Payment settlement waits for seller confirmation. The application records the handoff; it does not execute a bank transfer. The current standard delivery fee/final rider earning is PHP 50 per seller delivery, with a separately recorded PHP 15 logistics fee.

Confirmed findings and recommended repairs
-----------------------------------------

| Priority | Current behavior and practical effect | Recommendation |
| --- | --- | --- |
| Critical | Checkout deliveries omit sender/recipient details and `DeliveryItem` rows. The rider payload reads those delivery fields directly, so it receives missing addresses and an empty product list even when the buyer/seller supplied the data elsewhere. Both checkout paths reproduce the omission. | Use one shared shipment-creation service that copies the seller pickup address, buyer delivery-address snapshot, contact details, seller-specific item lines, COD total and instructions into the delivery. Repair historical rows from their linked order/store data after reviewing ownership. |
| Critical | Website checkout creates a delivery without opening a logistics pickup request. The mobile API opens one. Seller Schedule Pickup writes a different table and does not fill this gap. A website order can therefore miss the operational pickup queue. | Make both checkout paths use the same creation service. Keep an awaiting-seller-preparation stage, then open an actionable pickup request when the seller marks that seller's parcel ready. Connect the schedule to that request. |
| Critical | Pickup-rider handoff leaves `Delivery.status=picked_up` and `rider_id` pointing to the pickup rider. Assignment refuses reassignment from `picked_up`. The usual handoff therefore cannot proceed to a different delivery rider; the pickup rider's active capacity also remains occupied. | Finish the pickup leg on verified center receipt, preserve its rider/history, release its active workload, then create/activate a separately assignable delivery leg. Preserve the original tracking number. |
| High | Web Dispatch changes `parcel_status` from `sorted` to `dispatched`. Mobile center pickup requires exactly `sorted` and rejects the dispatched parcel with HTTP 409. | Use one guarded dispatch/handoff transaction shared by web and mobile. Staff release and rider receipt should identify the same parcel and assigned rider, and repeated confirmations should be idempotent. |
| Critical | A rider can be assigned while the seller order is still pending, even when the associated pickup request is rejected. Pickup approval is not a gate in assignment, and the rider actions do not verify seller readiness. | Enforce readiness, approved pickup, valid routing and a non-cancelled order at the backend assignment/accept/pickup boundaries. Do not rely only on button visibility. |
| Critical | Normal rider completion marks the delivery delivered but leaves `Order.status` unchanged. The buyer's Delivered filter still excludes that order; rating and return endpoints also depend on `Order.status=delivered`. | Synchronize order summary status and history from shipment/leg events in the same transaction. Publish consistent buyer/seller notifications. Keep delivery completion separate from later cash settlement. |
| Critical | Seller Confirm Delivery directly marks both order and delivery delivered without the rider OTP, proof or COD collection record. This path also bypasses creation of the logistics transaction and rider earning performed by normal rider completion. | Limit sellers to preparation and pre-pickup cancellation. Make the assigned final-delivery rider complete with buyer OTP; provide a separate permission-controlled, reasoned and audited logistics override for exceptional cases. |
| Critical | Seller cancellation changes the order, restores stock and voids the pending payment, but leaves the delivery active. A rider can still accept that cancelled order's assigned delivery. | Reuse one cancellation service for seller and buyer paths, with locks on order/deliveries, a physical-pickup guard, consistent cancellation of pending work, rider workload release, stock/voucher/payment handling and notifications. |
| Critical | Multi-seller mobile checkout tries multiple deliveries under one order; the live database has a unique index on `deliveries.order_id`. An isolated matching-schema checkout with two sellers returns HTTP 500 and rolls back. | Prefer the website's one-order-per-seller model for the first repair. A parent checkout may group the resulting store orders. Simply dropping the unique index would leave shared seller status, cancellation and singular delivery/waybill lookups inconsistent. |

Additional decisions needed
---------------------------

- Coverage currently checks complete address fields, not actual geographic serviceability. `DeliveryServiceability` explicitly defers matching until addresses and service areas share canonical location identifiers. Define seller pickup and buyer drop-off coverage using PSGC identifiers, and present unsupported destinations before accepting an order. The saved demo addresses are not proof of real delivery coverage.
- If parcels travel between different centers, sorting currently records the destination but does not represent a complete inter-center transfer/receipt leg. Define transfer custody, destination receipt and destination-center permissions before dispatching a final delivery rider.
- Define separate pickup-rider and final-delivery-rider compensation. The current handoff action does not create a pickup earning; the final delivery earning is credited to the delivery's final assigned rider.
- The normal COD completion path intentionally accepts short or excess cash and records a variance for reconciliation. Decide which discrepancies require staff approval before closing the money handoff. The seller remittance currently uses the expected order amount, rather than automatically reducing the seller's claim to the cash actually collected.

Recommended sequence
--------------------

Buyer checkout -> seller-specific order -> seller accepts and packs -> pickup request -> center approval -> pickup rider assigned -> seller pickup confirmed -> center receipt confirmed -> pickup leg closed -> scan and sort -> final rider assigned -> coordinated center release/rider receipt -> out for delivery -> buyer OTP and COD recorded -> delivery/order status updated -> rider remits -> seller confirms -> payment settled.

Failed delivery branches into a documented reattempt or return-to-center workflow. Cancellation becomes unavailable after physical pickup; later problems use the failure/return process rather than restoring stock while a parcel is still with a rider. A different-center destination adds a transfer/receipt stage before final-rider assignment.

Verification and live data
-------------------------

Read-only database inspection found:

- 35 orders and 34 order-linked deliveries.
- All 34 order-linked delivery records lack sender address, recipient address and delivery-item rows. The linked order/store/address records were not deleted by this review.
- 29 deliveries have no `PickupRequest`; 33 have no handling center.
- 15 active delivery records are linked to cancelled orders.
- No recorded sorting-center handoffs in the inspected order-linked delivery records.
- A unique index on `deliveries.order_id`, matching the multi-seller checkout failure reproduced in the isolated database.

An isolated SQLite copy was used for 80 existing tests covering rider completion, delivery OTP, stock/pickup validation, sorting/tracking, pickup badges and rider dispatch. Those tests passed. Nine additional diagnostic cases reproduced the nine findings above. The final run completed 89 tests / 556 assertions; the diagnostic assertions deliberately verify current faulty behavior, including the multi-seller HTTP 500, rather than claiming that the full flow succeeds.

Private logs, the nine diagnostic cases, live count/index snapshots and test configuration are in `%TEMP%/invoiz-order-flow-audit-20261030/`. Production queries ran inside a read-only transaction. No real orders, cash records, notifications, emails, application configuration, schema or application code were changed. This report is the only repository file added for this review.

Source references
-----------------

- [Website checkout](../../../routes/buyer/web.php): per-store order/delivery creation at lines 271-273 and 330-332.
- [Buyer mobile checkout](../../../app/Http/Controllers/Buyer/Api/OrderController.php): `checkout()` at line 182, shared order creation at 240, per-seller deliveries at 288, plus order-status-dependent filters/returns.
- [Seller order actions](../../../app/Http/Controllers/Seller/OrderController.php): `updateStatus()` at 135, `schedulePickup()` at 201, `syncDelivery()` at 254.
- [Rider-facing delivery payload](../../../app/Http/Controllers/Logistics/Api/RiderController.php): `deliveryPayload()` at 247.
- [Logistics delivery actions](../../../app/Http/Controllers/Logistics/DeliveryController.php): assignment at 841, web status updates at 1018, receive at 1397, sort at 1619 and dispatch at 1692.
- [Rider delivery actions](../../../app/Http/Controllers/Logistics/Api/RiderDeliveryController.php): completion at 279, center handoff at 477 and center pickup at 536.
- [Buyer cancellation](../../../app/Support/OrderCancellation.php): existing synchronized buyer-side cancellation implementation.
- [Remittances](../../../app/Support/Remittances.php): manual handoff calculation, sent/confirmed states and settlement gating.
- [Serviceability](../../../app/Support/DeliveryServiceability.php): address completeness and deferred geographic matching.
