# Unified order and return remittances

The user asked for the return-service escrow to follow the ordinary order-remittance process with release and receipt confirmation, without a separate payout account/receipt workflow or another place to look for pay.

## Current behavior

- Admin uses **Manage Payments** for both order and return-service shares. Submitted service and refund-recovery deposits appear in **Incoming escrow receipts** on that page. Opening the current receipt and confirming the actual incoming money is required once; this does not approve the customer agreement or set fees.
- Completed, funded return shares use **Release payout**, with one release confirmation. The service amount and recipient come from the existing agreed allocation. No separate per-service receiving-account form or outgoing receipt upload is required. A generated `RETURN-RELEASE` reference identifies an internal escrow release rather than pretending to be a bank reference.
- Riders, inter-hub drivers and hub staff/managers see order and return-service shares in one regular remittance list, with a Return # label. A released share waits for the entitled recipient's **Confirm received** action. Accepted return shares are included in ordinary rider/driver earnings summaries/history and the hub's combined confirmed-earnings total.
- Old return payout links redirect to the canonical regular pages. The standalone Admin Return delivery escrow sidebar item is removed. The earlier separate embedded return section and worker account-verification form are removed from the app.
- Customer refunds, unused escrow refunds and earlier non-service adjustments retain their own refund permissions and records. They are not counted as worker earnings. A conditional legacy adjustment link remains for actual outstanding non-service records.

## Integrity and compatibility

The combined feed projects existing `EscrowRemittance` and `ReturnAllocation` rows instead of copying payment amounts between ledgers. Source-qualified keys and confirmation paths (`order/{id}` and `return/{id}`) prevent numeric ID collisions. Old mobile APIs retain their original payloads, so older applications cannot accidentally accept a return ID as an order payout.

Return release still locks order, settlement and allocation, checks verified funding, earned work, an assigned recipient and unspent budget, and never exceeds the agreed amount. Duplicate release/accept operations do not issue another payment or credit. Actual bank transfers are not initiated by these controls. Neither payout records nor customer accounts are fabricated to demonstrate the feature.

`GET /api/remittances/incoming` is the shared, authenticated recipient list. `POST /api/remittances/incoming/{source}/{id}/accept` requires explicit receipt confirmation and delegates to the existing source ledger's ownership checks. Company/hub scope remains enforced. The old return JSON routes remain compatible for existing clients; the current app uses the shared list and source-aware confirmation.

No database migration or live payout backfill is required. Normal earnings reporting reads accepted return allocations without manufacturing another rider earning or original-delivery payout row.

## Validation

Isolated end-to-end tests cover all five reverse-trip service roles, release without receiving-account/proof setup, recipient confirmation through the shared API and driver/rider earnings updates. Additional checks cover unverified funds, incomplete work, missing confirmation, wrong recipients, repeated taps and order/return rows with the same numeric ID.

- Backend regression selection: **216 tests passed, 1,639 assertions**. Final settlement rerun: **106 passed, 734 assertions**. A subsequent participant-page/normal seller-route check passed **17 assertions**.
- Complete rider app suite: **434 tests passed**. Scoped analysis found no errors or warnings; two existing null-aware-element style suggestions remain in `hub_courier_service.dart`.
- Browser checks passed for combined Admin and hub pages at **320, 390, 768, 1280 and 1920 px**, including source-qualified dialogs, a one-checkbox return release, normal recipient confirmation, and no horizontal overflow. Screenshots were reviewed at mobile and laptop widths.
- Blade compilation and `git diff --check` passed.
- Read-only live checks show the pickup rider's existing order payouts and Return #5 pickup share together. The incoming return-service receipt is still submitted, so that share correctly waits for escrow confirmation. Deposit amounts/statuses, allocation amounts/statuses and existing original payouts match the earlier audit; no real payment was released or accepted as a demonstration.

## Published app and phone check

The rider/logistics APK is **1.0.7, build 9**, package `com.invoize.invoize_rider`, SHA-256 `e23bad5f32ec23d9e49e35eb4551f09f0a8d18c140d83b1b38f690270608e067`. The publication script checked package/version, signing-key continuity and increasing build number, and privately backed up the previous installer. The full public HTTPS download matched this checksum and the landing page displayed the new version. The buyer installer remained unchanged at 1.0.7 build 8.

The connected TECNO phone updated successfully in place. Android reports version 1.0.7/build 9 and the app retained its sign-in. Opening the regular remittance screen showed Return #5's final delivery share alongside ordinary order payouts, with the correct pending incoming-escrow confirmation message. No receipt-confirmation control was pressed on any actual payout.

The current notes are also available in [the UI guide](../../flows/return-refund-ui-guide.html#remittance-inbox).
