# Failed delivery: three attempts, seller return, or unclaimed storage

## Implemented policy

- Maximum three buyer delivery attempts. After failures one and two, the rider must physically return the parcel to the destination hub before logistics staff assign an eligible rider using existing controls.
- After failure three, another buyer-delivery assignment is blocked. The parcel returns to the destination hub and the seller chooses return or unclaimed storage.
- Unclaimed goods remain at the hub. They are not restored to saleable inventory. There is no reverse-transport charge for an unclaimed parcel.
- One outbound delivery budget covers the failed attempts. Its last-mile allocation is shared across the riders who recorded actual attempts; it is not charged three times. Other completed outbound services remain payable. Previously paid amounts are credited.
- A seller-requested return has a separate quote for its actual route and workers. Seller payment uses private receipt submission, administrator preview and verification, actual worker remittance, then recipient acceptance. Uploading proof alone does not establish payment.
- Buyer money and buyer return/refund claims remain separate. Cancellation of a prepaid unclaimed order uses the existing reviewed refund path, without restoring stock held at the hub.

## Operational sequence

1. Rider records a failure and confirms physical return to the destination hub.
2. After the third failure, seller or logistics opens the linked failed-parcel case from the order/delivery detail. Newly received third-failure returns open a case automatically when the new tables are available.
3. Seller confirms unclaimed storage, or requests a return quote.
4. For a return, logistics quotes the operational receiving hub and eligible truck/driver where inter-hub movement is needed. Server pricing supplies the fees. Seller explicitly accepts that quote version.
5. Seller transfers the outstanding amount through the configured payment channel and submits private proof/reference. The administrator opens the receipt, checks the actual incoming transfer, and verifies or rejects it. Rejected proof can be corrected under the same reference; no second transfer should be made merely to correct a receipt.
6. Fully funded inter-hub returns require assigned driver acceptance, departure, arrival photo, and separate receiving-hub confirmation. Arrival alone does not transfer custody to the hub.
7. Logistics staff assign an eligible seller-side rider. Rider confirms collection and uploads actual seller-handover evidence. Seller confirms physical receipt. Existing return-to-seller inventory/cancellation handling runs at receipt, once.
8. Administrator remits only funded, completed service allocations to configured payout accounts. Actual recipients confirm receipt through the existing incoming-payment controls. Unclaimed parcels still retain their unpaid outbound service allocations.

Rider access: **Settings → Failed-parcel Returns**. Assignment notifications also open this queue. Existing buyer-return jobs keep their separate workflow.

## Implementation map

- `app/Services/FailedDeliveryService.php`: state transitions, quotes, service ledger, funding and payouts.
- `app/Http/Controllers/FailedDeliveryController.php`: scoped web/API actions and private evidence access.
- `app/Models/FailedDelivery{Case,Fee,Payment}.php`: case, allocation and receipt records.
- `database/migrations/2026_11_15_220000_create_failed_delivery_resolutions.php`: three new tables only.
- `resources/views/shared/failed-deliveries/`: seller, logistics and administrator case screens.
- `routes/shared/failed-deliveries.php` and logistics API routes: authenticated feature endpoints.
- `rider_mobile/lib/screens/deliveries/failed_parcel_returns_screen.dart`: assigned funded-return work.
- Delivery assignment, return-to-seller, earnings and remittance integration points retain their existing authorization and physical-handover checks.

## Local verification, 2026-11-15

All PHP test runs used the safe laboratory bootstrap, SQLite `:memory:`, temporary storage and an ephemeral application key. No development MySQL database was connected to or migrated.

| Check | Result |
| --- | --- |
| FailedDeliveryResolutionTest + FailedDeliveryRedeliveryTest, PHP 8.5.9 | 26 passed, 197 assertions |
| Existing selected CI suite, PHP 8.5.9 | 44 passed, 277 assertions |
| Seven rider test files | 42 passed |
| PHP syntax, changed/new non-Blade files | 47 files passed |
| Changed Blade template compilation and PHP parsing | 7 passed |
| Targeted Flutter analysis, nine files | No errors or warnings; one existing `activeColor` deprecation info in Settings, exit code 1 |

The selected CI suite logs existing low-stock notification fixture warnings; these do not fail its tests.

Broader return/refund verification is not wholly green. A paired comparison of ReturnWorkflowTest and ReturnSettlementTest using the original shared classes versus the current shared classes produced the same 154 executions: 121 passes, 31 errors and two failures, with identical failing test names. The errors originate in duplicate `evidence_path` fixture setup. The two failing tests are:

- `ReturnSettlementTest::test_buyer_fault_mobile_api_confirms_seller_refund_and_private_receipt_without_admin`
- `ReturnSettlementTest::test_return_fee_upload_is_private_pending_and_requires_admin_receipt_preview_before_success`

This comparison is evidence of no additional failures in those suites; it is not a claim that every production path has been verified. No existing tests were skipped, deleted, weakened or fixed to conceal these results.

## Activation remains pending approval

The new migration has **not** been applied to `invoizdb`. No new feature APK has been published or installed. No commit, push or deployment was performed for this feature.

After explicit approval, inspect only the new migration and apply that file by its exact path, using the primary installation's existing configuration:

```powershell
php artisan migrate --path=database/migrations/2026_11_15_220000_create_failed_delivery_resolutions.php
```

Do not run all pending migrations: other unrelated pending migrations exist. Do not use reset, fresh, seed or rollback commands. The new file creates `failed_delivery_cases`, `failed_delivery_fees`, and `failed_delivery_payments`; Laravel also records its migration entry. Existing order/product tables are not altered by this migration.

Then build and verify a new rider APK with the matching Flutter SDK, update the approved landing-page installer, and install it on the authorized phone. These activation steps still require coordination. Existing historical third-failure parcels can be opened individually through their case link; there is no automatic bulk conversion of historical records.

## Limits and operational review

- Actual bank/e-wallet transfers remain manual and must be verified before releasing service funds. The feature cannot guarantee payment from a seller who refuses to pay; it records the outstanding obligation and blocks unfunded return transport.
- Unclaimed storage is not a disposal, resale or indefinite storage-fee policy. Those actions are outside this implementation.
- If a quoted driver/truck becomes unavailable after funding, transport fails safely pending logistics review; no silent reassignment or additional charge is made.
- End-to-end role testing against the live website, actual financial transfers, and real parcel custody have not been performed.
- Existing unrelated working-tree changes were preserved and must be reviewed independently before any future commit.
