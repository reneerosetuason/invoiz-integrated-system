# Dynamic inter-hub truck payments

**Current policy, version 3:** new quotes pay PHP 20 per parcel + PHP 0.20 per road kilometer per parcel + PHP 0.04 per actual kilogram per kilometer. Driver pay is the sum of individually rounded parcel fees. Product price/cost caps are retired. See [Current formula and returns](profit-protected-logistics.md). Confirmed historical snapshots retain their financial terms.

The manifest mechanism was implemented November 13, 2026 (`payment_version = 1`). New payment snapshots identify the current fee using `pricing_version = 3`. Completed earnings and confirmed historical payments retain their recorded terms; other pickup, hub and last-mile rates remain unchanged. Newly quoted return transport uses the current formula.

## Operating flow

1. In Fleet Management, record the truck's permitted actual payload and parcel capacity. Cargo dimensions are optional and do not block payment.
2. Create the transfer using the existing manifest workflow. Its detail page automatically resolves road distance from saved hub street addresses, and missing parcel measurements from seller products and ordered quantities. Existing recorded measurements and approved road distances take priority. There is no straight-line fallback. Review and confirm the automatic calculation; logistics staff do not retype these values.
3. Review actual cargo weight, the payment components, and shipment allocations. Invalid or overloaded manifests cannot be confirmed.
4. Confirm the payment. Confirmation locks the transfer, truck, manifest entries, and delivery measurements; checks the reviewed preview hash; stores the rate/measurement snapshot; writes exact centavo allocations; and reserves one escrow remittance identified by unique `TRIP-{transfer_id}`.
5. Load and dispatch using the existing staff/driver workflows. Dispatch requires the confirmed physical facts still to match the snapshot. Later rate changes do not change the confirmed payment.
6. Destination staff receive the parcels and complete the trip. There is one trip earning record, protected by the existing unique transfer/role constraint. Missing or damaged cargo holds the entire payout. Unresolved payment disputes and unsettled orders also block release. An open return request does not block payment for completed, funded outbound inter-hub work; its reserved or released allocation remains counted in return/refund accounting.
7. Admin Payments shows the truck-trip reference and opens its manifest. Release requires an actual bank reference and rechecks all constituent orders under locks. Trip payouts use individual release; the existing COD order batch does not include them. The assigned driver confirms receipt through the existing remittance acceptance flow.

## Rates and accounting

Admin Commission configures handling (PHP 20), distance (PHP 0.20/km), and actual-weight distance (PHP 0.04/kg/km). The actual payment directly uses these three components. Each parcel is rounded once, and its fee is both its seller deduction and its contribution to the one truck-trip payment. No additional per-order driver payment or percentage cap is applied. Legacy calculator rates remain available only to interpret historical snapshots. New return agreements use the reverse road distance and returned product weight under the same formula; weight capacity, parcel count and custody controls remain in force.

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

## Automatic inputs update (November 14, 2026)

- `ShipmentMeasurementService` fills missing parcel weight and dimensions from that delivery seller's order items. Variant weight overrides product weight when available. Grams and other existing weight units use `ShipmentWeightService` conversions. Every item must have valid data; partial totals are rejected. Existing measurements, including confirmed trip snapshots, remain authoritative.
- A single unit uses the product dimensions. Multiple units use a conservative stacked envelope: maximum length, maximum width, sum of all unit heights. This estimates the consolidated parcel, including empty envelope space for mixed sizes; packaging is not inferable from product data. Existing recorded packed measurements take priority. No optimal packing claim is made.
- New shipment creation snapshots missing dimensions. Older unconfirmed manifests derive missing values for review without writing on GET; payment confirmation freezes them with the road route and the existing rate snapshot. Changes to product data before confirmation invalidate stale preview hashes. Product edits after confirmation do not rewrite historical measurements.
- `HubRoadDistanceService` uses Photon address lookup and OSRM road routing. `config/hub-routing.php` supports `HUB_GEOCODER_URL`, `HUB_ROUTER_URL`, `HUB_ROUTER_PROFILE`, `HUB_ROUTING_COUNTRY` and `HUB_ROUTING_COUNTRY_CODE`. Default public endpoints need no key; they have no availability guarantee. Dedicated services can be configured without code changes. The default driving profile is not a truck restriction planner.
- Valid street/house matches must have matching city, country and street text. Ambiguous addresses, city-only matches, disconnected roads and service outages block confirmation. House matches require the matching building name or house number. If the building is unavailable, a unique matching street location can be used and is explicitly identified as a street-level hub location estimate. City centroids and unrelated nearby buildings are rejected. Successful geocodes and directional routes are cached for 30 days; address/config changes produce a new key. Distances are returned in road kilometers with three decimals. Map attribution is displayed beside the calculation.
- No schema change is required. Recalculation ignores client-supplied distance and measurement values. Escrow reservation, one payment per trip, exact allocations, overload checks, release gates and other delivery-worker rates retain their previous rules.

Read-only audit of the reported parcel `TRK-20261114-VAZ6` found product weight `0.30kg` and dimensions `6 * 6 * 16cm`; automatic values are 0.30 kg and 6 x 6 x 16 cm. The user explicitly approved replacing AILYN HUB (ID 10)'s placeholder `0989, YES` with a public demonstration location: `Lumban Town Hall, J. P. Rizal Street, Santo Niño`, Lumban, Laguna. The user also approved Photon/OSRM and example cargo dimensions of 300 x 160 x 160 cm for SPL 2002 (truck ID 3); its existing 1,500 kg payload was preserved. These are demonstration values and must be replaced with real operational specifications before real use. Before/after records are saved in `.tools/demo-hub-location-audit.json` and `.tools/demo-truck-cargo-audit.json`.

Live approved service verification returned a 91.972 km road route from the saved Makati street location to the public Lumban demo location. The original parcel now previews at 0.30 kg, 6 x 6 x 16 cm (0.000576 m3), with truck cargo volume 7.68 m3. That earlier physical-cost quote was PHP 559.86 under the retired formula; it is not the current per-parcel payment. Under version 3, 0.30 kg over 91.972 km pays PHP 39.50. The origin lookup has street-level precision, disclosed beside the road distance. No trip payment was confirmed or released by this verification.

Automatic-input test logs: `.tools/automatic-trip-tests.log` (allowlisted MySQL rollback tests) and `.tools/auto-trip-shipment-tests.log` (isolated SQLite shipment tests). Browser checks use the rendered read-only parcel/trip data with all browser requests intercepted locally; desktop 1365px and mobile 390px display four read-only measurement fields without page overflow or JavaScript errors. Screenshots: `.tools/auto-trip-1365.png`, `.tools/auto-trip-390.png`.

Final update validation: 56 MySQL tests / 208 assertions and 10 SQLite shipment tests / 45 assertions passed (66 tests / 253 assertions total). These include arbitrary hub pairs and reverse-direction route caching, automatic input display despite missing truck specifications, product quantity snapshots, stale product preview rejection, routing outages, city-centroid rejection, existing settlement behavior, and duplicate-payment prevention. Blade compilation and `git diff --check` passed. Live service verification used the explicitly approved Photon/OSRM endpoints. No destructive migration or test command targeted the development database; the only development master-data changes were the two explicitly approved demo records above.
