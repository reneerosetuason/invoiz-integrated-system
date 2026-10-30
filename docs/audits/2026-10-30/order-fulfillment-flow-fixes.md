# Order fulfillment fixes — 30 October 2026

The nine integration defects in the [original review](order-fulfillment-flow-review.md) have been repaired. The existing seller → pickup rider → logistics center → delivery rider → buyer sequence remains the operational flow.

## Operational sequence

1. Website and mobile checkout create one order, payment and tracking-numbered shipment per store. Each shipment contains contacts, pickup/drop-off addresses and item snapshots. Multi-store mobile checkout returns `orders`, retaining the first `order` for existing clients. Merchandise discounts, shipping waivers and COD totals balance across store orders.
2. The seller accepts, prepares and marks the parcel packed. Pending pickup records exist before packing, but routing/review badges and backend approval/assignment gates wait for readiness. Schedule Pickup records the schedule in the shipment history and connects to the same pickup request. Rescheduling a rejection requires a fresh review.
3. Logistics chooses an active handling center, approves pickup and assigns an eligible rider. Assignment, acceptance and shop pickup recheck order status, readiness, routing and approval under locks.
4. The pickup rider collects the parcel and records the center handoff. Staff confirm receipt before that rider's active assignment is released. Tracking, pickup milestones, rider identity and history remain intact. The pickup rider can subsequently view the completed leg without receiving another rider's action buttons or earnings.
5. Staff scan and sort the parcel. A different destination center requires origin dispatch and destination receipt, followed by scanning/sorting there. Destination staff can see incoming transfers; unrelated centers cannot receive or process them.
6. Logistics assigns a final rider and dispatches the parcel. The rider confirms center collection, then goes out for delivery. Staff release and rider collection are distinct, retry-safe acknowledgements. The app displays the center pickup address and the appropriate next action.
7. Buyer-linked rider completion verifies the buyer OTP, records COD/proof, and atomically updates delivery, order history, transaction, remittance and final rider earning. A failed financial write rolls these changes back. OTP verification itself is recorded as proof when no separate proof is supplied.
8. The rider's manual remittance and seller confirmation remain the cash-settlement process. Completing delivery does not automatically claim that the seller received the cash.

Sellers retain preparation, scheduling, waybills and pre-pickup cancellation. Their former direct handover/completion shortcuts no longer bypass rider custody or buyer verification. Exceptional web completion requires a logistics administrator, an explanation, an assigned rider, existing proof and COD information. Cancellation shares one locked stock/payment/voucher/shipment service and becomes unavailable after physical collection. Rider workload release preserves offline and inactive account settings. Same-rider reattempts preserve existing custody; a rider swap after failure requires a recorded center return.

## Main files

| Area | Files and changes |
| --- | --- |
| Shared shipment creation | `app/Services/OrderShipmentService.php`; buyer API `OrderController.php`; `routes/buyer/web.php`: populated shipments and consistent store-specific checkout. |
| Voucher allocations | `app/Support/Vouchers.php`; `app/Models/OrderVoucher.php`; `2026_10_30_180000_add_redemption_order_to_order_vouchers.php`: allocate quoted discounts and retain a shared redemption until all affected store orders are cancelled. |
| Workflow and cancellation | `app/Services/FulfillmentWorkflow.php`; `app/Support/OrderCancellation.php`; seller `OrderController.php`: readiness, order synchronization, custody release, safe cancellation and scheduling. |
| Logistics operations | Logistics `DeliveryController.php`, `PickupRequestController.php`, `SortingCenterController.php`; `Delivery.php` and `PickupRequest.php` models: approval gates, stage transitions, destination receipt and scoped access. |
| Rider API | Logistics API `RiderDeliveryController.php`, `RiderController.php`, `RiderHistoryController.php`: locked actions, center collection, contacts, order completion and completed-pickup history. |
| Web controls | Seller `orders/show.blade.php`; logistics `deliveries/show.blade.php`, `pickup-requests/show.blade.php`, `sorting-center/index.blade.php`: actions and explanations follow backend permissions/stages. Existing styling is retained. |
| Rider app | Delivery/earning models, delivery detail/card and history screen: center pickup locations, correct collection/departure controls, pickup history and read-only completed legs. A section-title overflow at 360px was fixed. |
| Repair and verification | `app/Console/Commands/RepairOrderFulfillment.php`; `OrderFulfillmentWorkflowTest.php`; checkout, completion and buyer fixture regressions; rider `center_collection_flow_test.dart` and `sorting_custody_test.dart`. |

## Local database repair

The nullable, indexed `order_vouchers.redemption_order_id` migration was applied. The existing unique `deliveries.order_id` index was retained. No delivery tables were dropped and no fee or environment settings were changed.

`php artisan orders:repair-fulfillment` previews repairs by default; `--apply` performs them and writes private before-change backups. This run inspected 35 orders, backfilled 33 shipments, closed 15 stale pre-pickup deliveries belonging to already-cancelled orders, and added 4 missing pickup requests. A second preview reported zero further automatic repairs.

The repair did not restore stock again, change money, invent delivery completion or choose geographic coverage. Missing snapshots were filled from linked order items, the shipping snapshot or buyer-owned saved address, and saved store pickup details. Existing nonblank shipment snapshots were preserved. Where only a current saved address exists, it does not establish the exact historical address at the time of purchase.

Private backups: `storage/app/private/fulfillment-repairs/20261030-010149-Ru6Fh0/`. They contain personal data and must remain private.

Remaining review/setup:

- **Order 8:** delivery store ownership conflicts with its item ownership. It was left untouched for review, including any associated settlement history.
- **Order 18:** a historical terminal order has no shipment. No new pickup was fabricated.
- **Orders 27, 39, 43, 44, 50, 53, 54 and 56:** logistics must choose handling centers. Packed orders appear in Pickup Requests → Needs Routing; preparation must finish first for the others.
- Geographic service coverage, separate pickup compensation and COD variance approval remain business-policy decisions. Current fees and reconciliation behavior were preserved. Inter-center transportation is recorded through staff release/receipt; no new transport-driver compensation policy was invented.

## Verification

- **244 backend regression tests / 1,823 assertions passed** in an isolated SQLite database matching the shared schema's unique delivery/order constraint.
- **53 focused completion/compliance/workload tests / 357 assertions passed.**
- **254 rider app tests passed**, including three center-collection widget scenarios at 360×800. Screenshots were inspected using the app theme and readable fonts.
- Rider `flutter analyze --no-pub`: **no issues found**.
- Debug Android APK built successfully: `rider_mobile/build/app/outputs/flutter-apk/app-debug.apk`.
- PHP syntax checks, Blade compilation and `git diff --check` passed.

Private logs and rendered screenshots are in `%TEMP%/invoiz-fulfillment-fix-20261030/`. The TECNO phone was not connected to ADB at the final device check; the new APK is ready to install so the phone uses the coordinated dispatch/collection controls. No commit or push was performed by this task.
