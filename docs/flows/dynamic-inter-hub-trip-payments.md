# Dynamic inter-hub truck payments

Implemented November 13, 2026. Applies to newly created fleet manifests only (`payment_version = 1`). Existing manifests, completed earnings, legacy linehaul adapters, return-service rates, and the other four delivery-worker rates retain their existing behavior.

## Operating flow

1. In Fleet Management, record the truck's permitted actual payload and usable cargo length, width, and height in centimeters. Cargo volume is calculated from those dimensions.
2. Create the transfer using the existing manifest workflow. On its detail page, record the actual packed-parcel weights and dimensions and an approved road distance with its source/reference. The source authorizer and an event timestamp are recorded. There is no straight-line fallback.
3. Review cargo weight, volume, independent utilization percentages, the higher utilization, the payment components, and shipment allocations. Invalid or overloaded manifests cannot be confirmed.
4. Confirm the payment. Confirmation locks the transfer, truck, manifest entries, and delivery measurements; checks the reviewed preview hash; stores the rate/measurement snapshot; writes exact centavo allocations; and reserves one escrow remittance identified by unique `TRIP-{transfer_id}`.
5. Load and dispatch using the existing staff/driver workflows. Dispatch requires the confirmed physical facts still to match the snapshot. Later rate changes do not change the confirmed payment.
6. Destination staff receive the parcels and complete the trip. There is one trip earning record, protected by the existing unique transfer/role constraint. Missing or damaged cargo holds the entire payout. Unresolved returns/disputes and unsettled orders also block release.
7. Admin Payments shows the truck-trip reference and opens its manifest. Release requires an actual bank reference and rechecks all constituent orders under locks. Trip payouts use individual release; the existing COD order batch does not include them. The assigned driver confirms receipt through the existing remittance acceptance flow.

## Rates and accounting

Admin Commission settings replace the editable fixed driver fee with base trip payment, per-kilometer rate, the 50% and 80% utilization bonuses, and the volumetric divisor. Defaults live in `config/earnings.php`: PHP 100, PHP 5/km, PHP 50, PHP 100, and 5000. Below 50%, the bonus is zero. Settings persist through the existing MySQL settings table and save in one transaction.

Actual weight controls payload checks. Parcel volume and truck volume use centimeters cubed divided by 1,000,000. Individual boxes must fit after axis-aligned rotation. This does not claim to solve a full three-dimensional packing problem.

Chargeable weight is the greater of actual and volumetric weight. Allocation uses deterministic largest remainders, with lower delivery IDs breaking ties. All payment components and final allocations use integer centavos; distance charges use integer thousandths of a kilometer. Extremely large multiplication values fail validation rather than lose integer precision.

Each shipment retains its existing seller-funded inter-hub budget (normally PHP 20), recorded once across successive trips. Actual trip allocations are separate from that budget. The signed difference is recorded as `platform_adjustment_cents` in the confirmed snapshot and as `platform_trip_adjustment` in allocation calculations. It is a platform reconciliation amount, not a new checkout charge or an additional seller deduction. Shipment driver remittance rows are zero for dynamic manifests, preventing a second payment beside the trip payout. When a shipment already completed a historical leg, its historical driver remittance and existing seller contribution remain intact; subsequent dynamic trips receive separate trip settlements. Refund funding checks retain the reserved/released seller contribution through `TripPaymentService::orderBudget()`.

The platform difference is an accounting contribution using the existing platform-covered deficit approach; this feature does not add a bank-transfer integration or a platform cash wallet. Admin still records the actual outgoing payment reference.

Before departure, cancellation, reassignment, parcel removal, and rerouting reverse a reservation and record its prior snapshot in the existing event trail. Staff must review and confirm again. Rerouting clears the recorded distance. Released or dispatched payments cannot be silently edited through this workflow.

## Changed files

- Calculation and lifecycle: `app/Services/TripPaymentCalculator.php`, `app/Services/TripPaymentService.php`, `app/Services/HubTransferService.php`.
- Accounting integration: `app/Support/EscrowEarnings.php`, `app/Services/ReturnWorkflowService.php`, `app/Support/RemittanceEntry.php`.
- Existing models extended: `app/Models/Delivery.php`, `app/Models/LogisticsTruck.php`, `app/Models/HubTransfer.php`, `app/Models/HubTransferParcel.php`, `app/Models/EscrowRemittance.php`.
- Controllers: `app/Http/Controllers/Admin/CommissionController.php`, `app/Http/Controllers/Admin/PaymentController.php`, `app/Http/Controllers/Admin/PaymentActionController.php`, `app/Http/Controllers/Logistics/FleetController.php`, `app/Http/Controllers/Logistics/HubTransferController.php`.
- Views: `resources/views/admin/commission.blade.php`, `resources/views/admin/payments.blade.php`, `resources/views/logistics/fleet/index.blade.php`, `resources/views/logistics/hub-transfers/show.blade.php`, `resources/views/logistics/hub-transfers/trip-payment.blade.php`.
- Configuration and routes: `config/earnings.php`, `routes/logistics/web.php`.
- Additive schema: `database/migrations/2026_11_13_120000_add_dynamic_trip_payments.php`.
- Tests: `tests/Feature/DynamicTripPaymentTest.php`, updated earnings form expectations in `tests/Feature/AdminSmokeTest.php`.

The existing storefront/product edits present before this task remain in the working tree and are not part of this feature.

## Validation and activation

The single additive migration was applied to the isolated MySQL sandbox at `127.0.0.1:3308/invoiz_ecommerce_sandbox` and to local `invoizdb`. No database refresh, destructive migration, or test command ran against `invoizdb`. Existing delivery/fleet/manifest/earning/remittance row counts and earning/remittance totals matched before and after the local migration. The migration leaves old manifests unversioned; it does not backfill historical compensation.

Passed:

```text
php artisan test -c phpunit.mysql.xml --filter='DynamicTripPaymentTest|InterHubRemittanceAcceptanceTest'
38 tests, 138 assertions
```

The new suite uses the existing allowlisted MySQL sandbox and transaction rollback, without `RefreshDatabase`. It covers bonus boundaries for both utilization measures, volume and distance calculations, exact allocations, invalid/missing measurements, overloads and individual fit, reservation retries, stale previews, historical rates, cancellation, actual fleet reassignment, dispatch gating, the full receipt/release/driver-acceptance lifecycle, late disputes, admin authorization, staff page rendering, and seller accounting conservation. Existing driver acceptance tests also pass using their established isolated fixture.

PHP syntax checks, Blade compilation, `git diff --check`, and `npm run build` passed. Browser screenshots and a visual responsive review were not performed.

Broader checks were attempted but remain blocked at fixture setup:

- `LogisticsAllocationReconciliationTest`: 11 setup failures, including missing required seller `line_of_business` and user `phone` values against the current sandbox schema; two cases pass.
- `ReturnSettlementTest`: 123 setup failures from adding an already-existing `evidence_path` column. These cases do not reach the trip-payment integration.

Those failures do not establish full regression compatibility. They need fixture maintenance before the broader suites can provide that evidence. Test logs are in `.tools/dynamic-trip-focused-tests.log` and `.tools/dynamic-trip-tests.log`; local migration before/after audits are in `.tools/trip-migration-before.json` and `.tools/trip-migration-after.json`.
