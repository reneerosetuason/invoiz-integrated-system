# Automatic return funding — 8 November 2026

Historical implementation note: the later [direct return agreement flow](direct-return-agreements.md) supersedes this deduction/reservation plan for new cases, at the user's request. Existing paid agreements keep their recorded terms. Use the current UI guide for new return proposals.

## What changes in the UI

New resolution proposals default **How to fund return fees** to **Automatic — use available verified escrow first**. The proposal still needs a complete transport quote, buyer/seller acceptance and admin responsibility review. Changing the terms requires fresh acceptance.

- **Buyer pays:** where verified funds cover the gross refund, deduct the agreed return charges from it and reserve those charges. The buyer sees the smaller net refund and does not pay the same fee again. If funds are unavailable or fees consume the refund, use a separate payment instead. An explicit separate-payment choice remains available.
- **Seller pays:** reserve the seller's available, unreleased proceeds from this order. Protect the buyer refund, original delivery service allocations, other stores' money and the remaining platform commission. The seller submits a receipt only for the displayed shortfall.
- **After agreement:** show actual buyer/seller reservations, remaining return budget and any separate refund-recovery shortfall. Viewing or refreshing the page does not move money. If original funds were reconciled later, show the amount now available to reserve and reduce the requested external deposit accordingly; reserve under the order lock when confirming funding or submitting that shortfall.
- **Before a physical-return quote:** show **Awaiting transport quote**, rather than a misleading zero return cost. The displayed refund is labelled as the amount before return fees.
- **On admin:** keep optional proposal edits and completed quote edits collapsed. Hide completed approval forms. Disable funding confirmation until its requirements are met, with a visible explanation.

## What each admin form means

| Form or button | Who uses it and when |
| --- | --- |
| **Change proposed terms — admin override** | The seller normally proposes responsibility, remedy, payer and refund amounts. Admin opens this only to correct or mediate terms. It clears old approvals. |
| **Complete the return route and fees / Save transport quote** | Logistics or site admin identifies the buyer collection hub, seller receiving hub and services actually required. Quote only real work; zero omits a service. Same-hub returns cannot charge inter-hub transport or a second handling hub. |
| **Admin responsibility decision / Record admin decision** | Site admin reviews the evidence and disputed responsibility once the full quote exists. This does not verify a bank deposit. |
| **Private refund receiving account / Verify refund account** | The buyer enters their bank/e-wallet receiving details. Complete details remain private to buyer and admin. |
| **Submit escrow payment** | The responsible payer uploads the actual incoming transfer receipt/reference for the displayed shortfall. A screenshot alone does not fund the return. Seller refund recovery is shown separately when original proceeds are already unavailable. |
| **Escrow payment review / Record deposit review** | Site admin checks the real incoming bank/e-wallet transaction, then verifies or rejects it with an explanation. |
| **Confirm funding & unlock resolution** | Site admin confirms that the approved return budget and buyer refund are secured. The system performs any remaining internal reservation and protects the refund from other returns. Physical returns can then be scheduled. |
| **Request admin review** | Buyer or seller disputes the terms or reports an issue. New collection/refund actions stay blocked during the dispute. |

The quote still supplies the service prices. This change automates funding and shortfall calculation; it does not introduce automatic route pricing or automatic bank transfers. Logistics handles actual custody and work; site admin verifies money and records transfers. Existing work verification and recipient remittance acceptance remain required before earnings.

## Examples

1. **Buyer fault:** agreed gross refund ₱4,599, agreed return fees ₱160, sufficient verified escrow. Reserve ₱160 for return services; the buyer receives ₱4,439. No second transport payment is requested.
2. **Seller fault:** agreed refund ₱100, return costs ₱100. Only ₱60 of the seller's proceeds remain available after protecting the refund, commission and original delivery costs. Reserve ₱60 and request ₱40. The buyer still receives ₱100.
3. **Seller funds already paid out:** those funds cannot be reserved again. Request the required new payment. Money held for another store is never used as this seller's wallet.
4. **Cancelled before collection:** release unused internal reservations to their original source. Only genuinely unused external deposits create a repayment; cancellation does not create a fictitious cash refund.

## Records and safeguards

The migration adds `funding_preference`, `seller_reserved_cents` and `refund_reserved_cents` to `return_settlements`. The existing buyer deduction remains in `reserved_cents`. Reservation amounts are integer centavos; events record reservations. Actual incoming deposits and outgoing refunds/remittances retain their existing ledgers and proof requirements.

Reservations use an order lock. Projected seller availability includes pending agreed merchandise refunds, so money needed for a refund or the remaining commission cannot also fund transport. The buyer refund receives its own hold when funding unlocks, and that hold is cleared only when the actual refund transfer is recorded or the case is properly cancelled. Original payout release checks include verified refund recovery and all protected return holds.

An existing agreement's null funding preference preserves its previous funding choice. Existing accepted/disputed proposals are not silently rewritten or automatically approved. Revise an unfunded agreement and choose **Automatic** if the parties want these new terms; resolve pending payments before revising.

## Validation

- The migration was applied to the configured database after a private snapshot of the seven affected ledger/workflow tables. Comparing existing row fields after migration confirmed all seven tables' records unchanged.
- **113 regression tests passed, with 899 assertions.** Coverage includes automatic buyer deductions; reconciled versus unreconciled COD; explicit separate deposits; seller partial/full shortfalls; late reconciliation; no writes on preview; released funds; multiple sellers; overlapping returns; remaining commission; cancellation; actual refund recording; original earned logistics release after recovery; recipient acceptance; private accounts; and buyer API integration.
- Browser checks cover buyer, seller, admin and logistics staff for buyer/seller-funded cases, plus unquoted admin and the UI guide, at widths 320, 390, 768, 1280 and 1920. No document overflow or JavaScript errors. Confirmed the buyer is not shown duplicate payment controls for deductions and admin cannot unlock an unfunded shortfall.
- PHP syntax, Blade compilation and diff whitespace checks passed. No actual bank transfer, new customer approval or physical custody event was created during validation.

See [the full UI guide](../../flows/return-refund-ui-guide.html) for the existing field-by-field descriptions and all later collection/refund/remittance actions.
