# Seller voucher activation and deactivation — 29 October 2026

Follow-up: the user confirmed the screenshot's three unassigned vouchers belong
to their store. Their ownership and the management listing have now been
corrected; see [voucher-ownership-repair.md](voucher-ownership-repair.md). That
follow-up supersedes the platform classification and no-migration note below
for these specific records.

The seller voucher table included platform offers in the edit dialog, while the
update handler correctly allowed only store-owned vouchers. Those platform edits
returned 404. Platform offers now remain visible as read-only references, and a
seller's own vouchers have direct Activate/Deactivate buttons.

Changes:

- `routes/seller/web.php`: authenticated seller POST endpoint
  `/seller/vouchers/{voucher}/status`, named `seller.vouchers.status`.
- `app/Http/Controllers/Seller/VoucherController.php`: validated status-only
  update scoped to the seller's record IDs, including safe legacy user-ID keys.
  Locks the voucher row in a transaction, matching final checkout's voucher lock.
  Claimed and unclaimed vouchers can be toggled without resubmitting offer terms.
  The edit configuration now includes only owned vouchers and claim counts.
- `app/Models/Voucher.php`: claims relation for the edit lock and claim count.
- `resources/views/seller/vouchers/index.blade.php`: direct status controls,
  Active/Inactive labels normalized for both text and boolean database schemas,
  read-only platform entries, and locked offer-detail fields for claimed vouchers.
  Status remains editable. Existing claimed-voucher deletion protection is retained.
- `public/assets/seller/js/vouchers/index.js`: uses each voucher's server-generated
  update URL instead of constructing it from the listing URL. The asset URL has
  a modification-time version so browsers receive the updated script.
- `app/Http/Controllers/Buyer/BuyerFeaturesController.php`: fixes the buyer website
  voucher list's seller-ID namespace mismatch, allowing eligible store vouchers
  to appear again when reactivated without crossing colliding seller/user IDs.
- `resources/views/buyer/vouchers.blade.php`: correct store ownership labels and
  an explicit Platform offer label for vouchers without a store owner.
- `tests/Feature/SellerVoucherStatusTest.php`: nine integration tests covering
  activation/deactivation, claim locks, authorization, missing/foreign IDs,
  boolean status schemas, edit configuration, buyer web/API visibility, stale
  checkout rejection, reactivation, existing orders and cancellation.

Verification:

- 95 backend regression cases passed across seller status, checkout voucher
  scoping, buyer web/mobile integration, commerce, product chat and the existing
  voucher-ownership security regression: 757 assertions in total.
- Real Chromium checks at 360px and 1280px verified both status form targets,
  Activate/Deactivate payloads, the claimed/unclaimed edit dialogs, normalized
  status selections, locked claimed terms and read-only platform entries.
  Zero JavaScript errors or document overflow. Screenshots were inspected.
- Registered routes verified; no route cache was present. PHP/JavaScript syntax
  checks and `git diff --check` passed.
- All fixtures used isolated in-memory SQLite. Browser write requests were
  blocked; no live vouchers, orders, carts, payments or claims were modified.

Buyer voucher listing, claim, code validation and final checkout already consult
active status; integration tests verify they reject a voucher deactivated after
it was claimed. A page opened before deactivation can retain an old display until
refreshed, but its stale voucher cannot be used to place a new order. Existing
orders keep their original discount. Cancelling one releases its claim without
reactivating an inactive voucher.

Sellers cannot change platform offers or another store's vouchers. No database
migration or mobile rebuild is required. Existing staged and unrelated working
changes are preserved, and no commit was made.
