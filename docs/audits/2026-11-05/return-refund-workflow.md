# Return/refund workflow implementation and checks

Date: November 5, 2026.

Process diagrams: [printable flowcharts](../../flows/order-return-escrow.html) and [Mermaid diagrams with the operating guide](../../flows/order-return-escrow.md).

## Changes

- Added the authenticated `/return-center` for buyer, seller, logistics, rider and marketplace admin accounts. Cases and actions are scoped to the owning buyer/seller, assigned rider, handling hub/company or the administrator's role.
- Added reverse-custody records independently of forward deliveries: collection assignment, rider collection, hub intake, optional return manifest transfer/arrival, dispatch back to seller, seller receipt, refund sent and buyer receipt confirmation. This avoids generating a second COD collection or a duplicate forward-delivery earning.
- Seller approval queues collection. Approval cannot subsequently be silently rejected after custody begins. Rejection of a new request requires an explanation.
- Seller receipt does not claim a refund occurred. Saleable stock is restored only on an explicit resellable inspection choice. Damaged failed-delivery returns remain out of saleable inventory.
- A refund requires reconciled funds, a real outgoing transfer reference, private image proof and the administrator's attestation. The funding preview and locked submission calculation preserve earned logistics fees and account for previous releases/refunds. Recovery shortfalls require a separately verified incoming transfer reference, proof and amount.
- The buyer confirms receipt on the website or buyer app. The case stays open until confirmation; duplicate confirmation is harmless. Zero-cash returns close without a fabricated payment.
- Prepaid cancellation and approved disputes create payment-review cases. Payment-only cases have zero physical return quantity and separate merchandise/shipping accounting, so they do not restore stock or consume purchased return quantities.
- Active return/dispute cases block unreleased payouts, including COD batches. Withdrawal approval and payout completion recheck the available seller balance. GCash payout ceilings include refunds and verified recovery funding.
- Merchandise refunds reverse merchandise commission and remaining seller allocations while preserving completed logistics fees. Partially refunded sales remain accounted for in remaining seller balances and admin financial totals. Later delivery settlement cannot overwrite refund status.
- Fully refunded GCash cannot restart fulfillment. Completed logistics fees can still be paid from sufficiently funded escrow/recovery after the case closes.
- Fixed failed-parcel seller authorization and stock restoration to act on the parcel's seller, rather than every store in an older shared order.
- Added buyer refund confirmation/proof controls and rider mobile return collections, with authenticated API endpoints.

Transfers are manual: these changes record actual transfers and human verification; no GCash/bank transfer API is implemented. Return manifests use recorded transport references and hub handoff confirmations rather than creating forward-route earnings. Additional reverse-transport charges are not automatically generated.

## Database changes applied

Only these targeted migrations were applied; historical migrations and existing application records were not reset:

1. `2026_11_05_180000_create_return_workflows`: custody/payment-review history and private proof references; expands payment dispute status to support awaiting refund. Existing approved/received cases retain their earlier decisions.
2. `2026_11_05_190000_allow_shipped_order_status`: the installed MySQL order enum did not permit `shipped`, although rider pickup writes that status. The migration preserves existing values and adds it.
3. `2026_11_05_200000_scope_delivery_uniqueness_to_seller`: the installed schema uniquely constrained `deliveries.order_id`, preventing separate seller parcels for a shared order. A unique `(order_id, seller_id)` index replaces that restriction, retaining an index for the order foreign key.

## Existing-record review

- No payment transaction had refunds greater than its recorded payment amount at review time.
- Order **#74** was already cancelled, had an uploaded GCash receipt and lacked a refund case. It was queued as a pending payment-only refund review, including unused shipping. Its stock and payment balances were not changed, and no refund was claimed or sent. Admin must verify the receipt and obtain the required seller-held funds before payment.
- Order **#73** was already confirmed without a funded escrow record. It is flagged in the admin return center. Existing funding gates block further fulfillment/release pending seller remittance and admin verification. No deposit or payment verification was fabricated.
- One historical closed return lacks the new proof/confirmation history. It remains a legacy record; it was not retroactively labelled as a verified buyer receipt or refunded a second time.

## Validation

Completed checks:

| Area | Result |
| --- | --- |
| Return workflow, seller visibility, checkout GCash proof, voucher scope, account-deletion holds and commerce regression suites | **100 tests passed, 716 assertions** |
| Buyer Flutter return / cancellation / refund confirmation | **17 tests passed** |
| Rider Flutter return collection / remittance service / payout receipt controls | **5 tests passed** |
| Two-rider order lifecycle through seller, pickup, hub, last-mile delivery and proof | **1 smoke test passed, 55 assertions** |
| COD reconciliation | **1 smoke test passed, 5 assertions** |
| Failed/damaged parcels, seller ownership, shared-order stock and repeat confirmation | **6 smoke tests passed, 53 assertions** |
| Stage-aware assignment and multi-hub routing | **3 smoke tests passed, 92 assertions** |
| Return pages for buyer, seller, staff, rider and admin at 320/390/768/1440px | **20 browser checks; no document overflow or script errors** |
| Blade compilation and changed application PHP syntax | **Passed** |

The core 100 tests use isolated SQLite fixtures. Their older fixtures were updated to include required return evidence, verified account identities, the designated logistics admin identity, current logistics fees and the new receipt/payment separation; production authorization was not weakened to accommodate tests.

The additional logistics smoke checks use an in-memory schema-only snapshot of the installed database, without copying customer records. Unrelated required contact fields are nullable in that temporary fixture schema. The order happy-path fixture was adapted to the designated admin identity and current `shipped` / pickup-fee expectations. These smoke checks exercise business routes but do not constitute a complete MySQL integration suite.

The rider workspace points at its bundled Flutter SDK. Its existing compiled shader/tool cache is inconsistent; deterministic widget tests disable the GPU ink splash in test themes, without changing the application theme or controls. The collection and remittance interactions pass. Android APK rebuilds, physical device tests and live financial transfers were not performed in this review.

Private previews and test logs live under ignored `.tools/`. No real payment transfer or bank verification was performed by the automated checks.
