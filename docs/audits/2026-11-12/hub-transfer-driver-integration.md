# Hub transfer driver branch integration

Date: 2026-11-12

## Integration

- Fetched `origin/feature/hub-transfer-driver-application` and fast-forwarded local `main` from `bf37207b59c80540b1d4c7067414da8c551c2533` to `eff498f38d5a0db2052abe0ba4aa852e8e1a9a15` (23 commits).
- Nothing was pushed. Existing return UI, cleanup changes, and the user's untracked SQL backup remain in the working tree.
- Backup branch: `backup/pre-hub-transfer-driver-merge-20261112`.
- Recovery stash retained: `810fa7104385297bbe84731a1b64cdaf03953aaf`.
- Local file backup: `storage/framework/cleanup-recovery-hub-integration-20261112.zip` (ignored by Git).
- The single modify/delete conflict retained the cleanup deletion of `public/assets/seller/js/orders/waybill.js`; retained views use the logistics delivery script.
- Hash comparisons verified preservation of prior local edits, deletions, and untracked files. The redesigned Return Center differs from its saved version only by the incoming blocked-driver filter.

## Compatibility

The incoming feature adds logistics operations, explicit scan confirmation, document replacement approval, and driver eligibility checks. Physical parcel operations now require hub staff; admin/manager access is for oversight. Suspended/inactive drivers are blocked without changing return settlement amounts.

The incoming branch does not modify `EscrowEarnings.php`, `Payments.php`, `EscrowRemittance.php`, `ReturnWorkflowService.php`, or the shared return routes. All 576 existing method/URI/name route combinations remain; 16 routes were added (592 total).

Only the additive `2026_11_06_000001_create_rider_document_change_requests_table.php` migration was applied to the application database. No bulk migrations, database rebuilds, payouts, or return state transitions were run. Counts and SHA-256 hashes of all rows in 13 production order, payment, return, and earnings tables were identical before and after integration.

Local compatibility fixes remain uncommitted alongside the preserved work:

- `database/factories/UserFactory.php`: provide the required synthetic phone in test users.
- `tests/Feature/RiderDocumentChangeRequestTest.php`: provide required test user phones.
- `tests/Feature/HubTransportDriverTripEarningsTest.php`: complete required user, seller, category, and address fixtures; use aligned seller/user fixture IDs for the legacy sandbox foreign key. Earnings assertions are unchanged.
- `tests/Feature/InterHubRemittanceAcceptanceTest.php`: supply the application table queried by the expanded courier dashboard in its SQLite fixture. Remittance assertions are unchanged.
- `rider_mobile/lib/screens/profile/profile_screen.dart`: remove unused imports reported by the analyzer.

No additional production order, refund, or earnings logic was changed to accommodate the integration.

## Verification

Tests ran on in-memory SQLite or the explicitly isolated MySQL sandbox at port 3308, never on the application database.

| Checks | Result |
| --- | --- |
| ReturnSettlementTest and ReturnWorkflowTest | 154 passed; 1,111 assertions |
| Commerce, buyer API, escrow, admin/hub/inter-hub remittance, payout account, voucher, seller return visibility | 111 passed; 962 assertions |
| Operations, role separation, scan authorization/lookup, origin receipt, inactive hubs, privacy, vehicle eligibility, barcode contract | 93 passed; 1,071 assertions |
| Inter-hub acceptance, signed document links, vehicle document synchronization | 20 passed; 79 assertions |
| MySQL rider document approval and hub driver trip earnings | 26 passed; 139 assertions |
| Rider scanner, documents, manual scan, returns and remittance widgets | 29 passed |
| Additional return collection, job summary, return inbox and courier remittance widgets | 13 passed |
| Vite production build | Passed |
| Blade compilation and route enumeration | Passed |
| Shared backend connection check | Passed; 92 models share the application connection |
| Prior local work preservation | Passed |
| Production financial row hashes | Unchanged across 13 tables |

Focused Flutter analysis has no errors or warnings; two existing `unnecessary_underscores` informational notices remain in the profile image error callback. PHP 8.5 emits a vendor PHPUnit/Collision deprecation notice. Neither blocks the passing checks. This is targeted regression coverage, not a claim that every repository test or live user journey was exercised.

Detailed logs and JUnit results are in the local temporary directory `invoiz-hub-integration-20261112`.
