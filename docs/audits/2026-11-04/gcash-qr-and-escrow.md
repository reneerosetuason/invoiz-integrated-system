# GCash QR checkout and manually reconciled escrow

Implemented for the Laravel website, buyer Flutter app, and rider Flutter app.

## Operator setup

1. Admin: **Payments → Review GCash escrow deposits & receiving account** (`/admin/payments/gcash-escrow`). Enter the real platform receiving account and transfer instructions; optionally upload its QR.
2. Seller: **Account Management → GCash receiving account** (`/seller/account`). Enter the account name and upload its receiving QR.
3. Buyers can now select GCash for that store. Stores without a receiving QR cannot accept GCash checkout; COD remains available. Updated November 5: buyer checkout and receipt upload no longer require platform receiving settings. Seller escrow deposit still requires those settings, and order acceptance still requires admin-confirmed full funding.

No account numbers, QR images, paid statuses, or confirmed balances were invented or populated in the local business database.

## Workflow

1. Checkout displays each store's account, receiving QR, and server-calculated amount including shipping and selected vouchers. Each store gets a separate payment and private receipt. A combined receipt cannot cover multiple receiving accounts.
2. The seller views the buyer receipt and explicitly verifies receipt of the whole payment against their own GCash transactions.
3. The seller transfers the **entire order payment, including shipping**, to the configured platform receiving account. The seller submits the exact amount, a unique transfer reference, and a separate private transfer receipt.
4. Admin checks actual incoming funds and confirms or rejects the deposit with a reconciliation note. Rejected deposits may be corrected. Uploading proof or submitting a reference does not confirm funds.
5. Only confirmed full deposits unlock order acceptance and fulfilment. Order and delivery model guards also cover alternate update paths.
6. After delivery, the existing allocation formula prepares the platform, seller, hub, pickup, linehaul, and last-mile shares. Admin records each release separately with an actual transfer/reference. GCash is excluded from the legacy COD batch release.
7. The rider app's **Earnings → Remittances & escrow payouts** page shows incoming shares. Admin release alone does not add GCash earnings. The assigned recipient must confirm the payout arrived; acceptance records the corresponding rider earning once and refreshes the earnings summary. Pickup pay follows the same rule. Sellers can acknowledge their own net payout under Remittances; hub acceptance uses the existing logistics page.

These are records of **manual external transfers**. The application does not debit GCash, move bank funds, verify receipts with a provider, or create a regulated escrow account automatically. Operators must use the actual receiving account and reconcile transfers.

## Accounting and access controls

- GCash deliveries collect zero cash and never create a rider-to-seller COD cash debt. COD's existing cash retention and earnings behaviour remains available.
- Receipt verification, deposit submission, admin funding confirmation, payout release, and payout acceptance are distinct states.
- Full funding, release, acceptance, and allocation refreshes serialize on the order. Repeated submissions/reviews/acceptances cannot duplicate credits; accepted allocations are preserved during later synchronization.
- Payouts require completed delivery, an assigned recipient, positive amounts, no unresolved return/dispute, and sufficient confirmed escrow balance. Existing fee rules can explicitly produce a platform-funded deficit; a release beyond buyer-funded escrow is blocked for accounting review rather than silently manufacturing that subsidy.
- GCash seller shares never also enter the legacy withdrawal wallet, preventing a second payout of the same share.
- Receipt and escrow proof images stay on private storage. Buyer receipts are scoped to their buyer/seller; escrow transfer proof is admin-only. Receiving QRs are intentionally public buyer-facing images.
- JPG, JPEG, PNG and WebP images are limited to 5 MB. Failed checkout/deposit operations remove newly uploaded files. Prior rejected deposit evidence is retained in private storage with audited references.
- Existing GCash rider earning rows remain retained; ordinary earnings/history queries show them only after the matching remittance is accepted, including for archived orders.
- Cancellation does not send money back automatically. The admin deposit page flags cancelled/returned/refunded orders for manual reconciliation; stakeholder releases remain blocked. Existing refund/dispute workflows must record the appropriate refund after the actual transfer.

## Database

Applied locally after a full backup:

`2026_11_04_140000_add_gcash_escrow_workflow.php`

- `sellers.gcash_qr_path`, `sellers.gcash_account_name`
- `gcash_escrow_deposits`: one record per order; exact amount, seller verification, transfer reference/proof, review state, admin confirmation and note
- `gcash_escrow_settings`: platform receiving instructions and optional QR

The migration is additive and forward-only to retain financial history. Other environments need this specific migration after the existing payment-proof and escrow schema migrations. Do not reset the database or indiscriminately run older imported migrations.

Local backup: `storage/app/private/logistics-before-repair-20261104-131922.sql`.
SHA-256: `557b33d24343a64405157cd2086e8b799072a2b1410b105ce7a7ab93e28da72b`.
The local schema audit found 101 tables. This migration adds schema without rewriting business records. Both new tables remain empty locally; no actual payment was confirmed or remitted by this implementation session. Tests use isolated SQLite data.

## Validation

- **61 isolated SQLite feature tests passed (433 assertions)** covering checkout QR/amounts, missing setup, separate store receipts, receipt ownership, failed-upload cleanup, full-funding gates, admin-only confirmation, rejection/resubmission, duplicate references, private deposit proof, release limits, premature/batch release blocking, assigned-rider acceptance, API earnings before/after acceptance, legacy earnings visibility, pickup payouts, COD visibility, and the earlier account-deletion/voucher workflows.
- **14 focused buyer Flutter tests passed**, covering receipt validation/multipart fields, COD reset, voucher selection, and account deletion.
- **4 focused rider Flutter tests passed**, covering service parsing, COD handoff, and confirmation/cancellation of incoming payout acceptance.
- Browser checks render isolated database fixtures at 390 px and 1280 px: receiving details, independent store receipts, file validation, COD reset, unavailable-store blocking, admin review, no horizontal overflow or script errors.
- Buyer analysis reports no errors/warnings. Analysis of all six changed rider files reports no issues. Rider-wide analysis reports three existing unused imports in `lib/screens/profile/profile_screen.dart`; those same imports are present at HEAD.
- PHP syntax checks passed for 68 changed/new PHP files; Blade compilation and `git diff --check` passed.

Validation artifacts and private database snapshots are under ignored `.tools/` and `storage/app/`; this document is the reviewable setup reference.
