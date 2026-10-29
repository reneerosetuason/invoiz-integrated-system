# Store voucher ownership repair — 29 October 2026

The seller's screenshot showed WELCOME10, SAVE15 and FREESHIP as platform offers.
The earlier UI fix treated their null `seller_id` as platform ownership, but had
not verified the intended owner of those specific records. The user confirmed
that all three belong to the store **akjshd**.

Read-only inspection found store `sellers.id=5`, `users.id=3`; the three voucher
records had no seller assigned. This store therefore had zero linked vouchers,
and the table had misleadingly shown unassigned offers as its own list.

Completed repair:

- Linked exactly WELCOME10, SAVE15 and FREESHIP to seller record 5. Their IDs,
  codes, offer terms, status, redemption counts and claims were preserved.
- Seller voucher management now queries only that seller's safe record IDs.
  Newly created vouchers use the authenticated store's ID, regardless of a
  submitted owner field. Owned vouchers expose Edit and Activate/Deactivate.
- Claimed or previously redeemed vouchers keep their offer terms locked while
  their active status remains editable. Historical `order_vouchers` rows also
  prevent term edits/deletion if an imported record has no claim or usage count.
- Added `order_vouchers.seller_user_id` as an internal historical scope snapshot.
  Zero records a platform-wide scope; a positive value records the store's user
  ID. Existing rows were backfilled **before** the ownership change, and new
  redemptions capture scope automatically. Seller discount, shipping waiver and
  delivery collection calculations now use this snapshot for existing orders.
- Applied only `2026_10_29_000002_snapshot_order_voucher_store_scope.php` locally.
  Its rollback retains historical financial snapshots.

Changes are in `app/Http/Controllers/Seller/VoucherController.php`,
`app/Models/Voucher.php`, `app/Models/OrderVoucher.php`, `app/Models/Order.php`,
`resources/views/seller/vouchers/index.blade.php`, the new migration, and
`tests/Feature/SellerVoucherStatusTest.php`.

Verification:

- **122 backend tests passed, 959 assertions** using isolated in-memory SQLite.
  Coverage includes store creation/edit/status controls, authorization, voucher
  scoping, deactivation, migration snapshots, ownership repair, historical
  discounts, existing orders, claims, buyer web/mobile, commerce and security.
- Backed up the three live voucher records, their applied-voucher records and
  historical financial figures to the local temporary audit directory before
  changing ownership. The repair ran in a transaction with before/after checks.
- All **five affected historical orders** retained identical stored totals,
  seller discount/waiver/collection calculations, delivery collection records
  and payment records. No order, payment, cart or claim was changed.
- Read-only rendering of the actual repaired store page returned HTTP 200 and
  showed all three vouchers with editable status controls. Chromium checks at
  360px and 1280px verified each edit action and deactivate form target/payload,
  no platform-only notices, zero JavaScript errors and no document overflow.
  All browser write requests were blocked; rendered screenshots were inspected.
- PHP syntax and `git diff --check` passed. No commit was made.

The live backup, repair script, result JSON and browser artifacts are under
`%TEMP%/invoiz-voucher-ownership-20261029`. The repair is specific to the user's
confirmed local records; the migration does not assign arbitrary voucher owners
on other installations. Offer behavior, including FREESHIP's existing fixed
discount type, was preserved.
