# Buyer/seller return agreements and automatic service fees

Implemented 8 November 2026. This replaces the earlier automatic-deduction plan for new return cases.

## The normal flow

1. Buyer submits an item return request with evidence.
2. Seller selects buyer or seller responsibility, proposes the remedy and explains it. Sending the proposal records the seller's agreement.
3. The system calculates all applicable return services from **Admin → Commission**. Fees are a snapshot of the configured rates, not editable return-form inputs.
4. Buyer accepts the exact proposal, declines it, or sends a suggestion. Declining or suggesting changes clears buyer acceptance and requires a revised seller proposal. History remains visible, including the speaker. Old versions cannot be accepted.
5. After both agree, the responsible party pays the entire new return-service bill separately to the platform account and submits their receipt. Buyer fault means buyer pays; seller fault means seller pays. There is no buyer-refund deduction, seller-earnings reservation, compensation input or routine admin responsibility approval in this flow.
6. Payment staff verifies the actual incoming transaction. With the fees, original refund funds and refund receiving account secured, the system activates the return automatically.
7. Seller schedules pickup. Existing hub/rider custody and evidence controls move the item back to the seller. The actual refund and recipient remittances keep their transfer-reference/proof requirements.

Manual bank verification is still needed because the system has no bank payment callback. A receipt screenshot alone is not verified money. Admin handles bank verification, refunds, remittances and genuine payment/custody exceptions; admin does not determine normal fault or enter per-case fees.

## Rates and money

| Service | Automatic amount |
| --- | --- |
| Buyer-side collection rider | Commission pickup rider rate × returned quantity |
| Buyer-side collection hub | Commission origin hub rate |
| Inter-hub driver | Commission inter-hub rate, only when hubs differ |
| Seller-side receiving hub | Commission destination hub rate, only when hubs differ |
| Delivery to seller | Commission last-mile rider rate |
| Existing exchange replacement dispatch | Commission last-mile rate for the replacement dispatch from the seller-side hub |

At rates ₱30 / ₱30 / ₱20 / ₱30 / ₱50, one same-hub physical return costs ₱110; one cross-hub return costs ₱160. Actual figures use the saved settings. No-return refunds create no transport allocation. No extra admin-service percentage is invented for return transport. Original-sale commission reversal remains in the merchandise refund ledger.

The item refund is not reduced by these new service fees. If the original buyer money has already been paid out or is insufficient after completed original logistics work, the seller still funds the refund recovery separately. Neither another store's escrow nor the newly deposited transport budget can substitute for the original buyer refund.

Paid return service allocations become eligible only after independently verified work. Rider, driver and logistics-hub remittances still require recipient acceptance before accepted earnings. Unused verified external fees remain repayable to the actual payer through reconciliation.

## UI and compatibility

New agreements use `workflow_policy=direct`; the model's mutual-agreement rule does not require `admin_decided_at`. Proposals force separate payment and derive payer from responsibility even if a caller sends old funding or payer fields. Manual quote and admin fault-decision actions are rejected for direct agreements. Fee snapshots, proposal versioning, payment locks and order-level locks prevent silent changes or duplicate spending.

The new page has a read-only service table, seller proposal, buyer acceptance and feedback, separate payment receipt, verified refund account, collection scheduling and conversation history. Optional exceptional bank/custody corrections preserve the agreed fees and mutual consent. The return-center refund preview now excludes the current refund's own reservation, so it does not falsely request the same secured refund again.

The buyer Flutter screen supports the same direct-agreement payment gate, comments, declines and conversation. Its legacy deduction display remains only for historical agreements. The proposal dialog now retains its controllers until the closing route transition finishes, preventing a disposed-controller crash.

Already paid, funded or moving historical agreements keep their recorded funding and workflow. The migration only switches untouched drafts automatically. Return #3 had an unpaid disputed version 5, so a separate guarded, backed-up upgrade preserved its conversation, moved it to `revision_requested`, selected separate payment and incremented to version 6. Seller must edit and resend before buyer acceptance. Its order, original payment, deliveries and original remittances were verified unchanged.

Return #3 also exposed an older route limitation: its completed original delivery crossed companies (AILYN HUB → NorthStar Caloocan Hub in reverse), while the old return quote demanded one company. Direct returns now allow that exact verified original route to be reversed; arbitrary cross-company destinations remain blocked. The collection company's driver is assigned, the destination hub must confirm receipt, and only the funded agreed seller hub is offered for dispatch. The live rate estimate for that route was ₱160 at verification.

## Verification

- 130 backend tests passed with 999 assertions, including automatic same/cross-hub rates, returned quantity, snapshot stability, forced payer, no escrow deductions, negotiation/versioning, no payment before agreement, correct payer, payment verification, automatic activation, account correction, actual refunds, private data, verified original cross-company route reversal, rejection of arbitrary cross-company routes, and legacy regression coverage.
- Two buyer agreement widget tests passed; the changed Dart screen and tests have no analyzer issues.
- Browser checks passed at 320, 390, 768, 1280 and 1920 pixels for 20 role/state pages plus the guide. No document overflow or JavaScript errors; payment and proposal controls match role and state.
- Migration applied after a private seven-table backup. Existing financial/custody records were preserved. No test created an actual bank transfer or fabricated an actual handover.
- Buyer release APK 1.0.4 (build 5) built for the shared production API; publication verifies package, version, signing-certificate continuity and checksums, and backs up the previous installer.

The full user-facing instructions are in [the UI guide](../../flows/return-refund-ui-guide.html).
