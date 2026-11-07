# Return agreements, funding and remittances

The UI guide is [return-refund-ui-guide.html](../../flows/return-refund-ui-guide.html). Open `/return-guide` while signed in, or use **How this works** / **Button-by-button guide** from the return screens. The guide lists fields, buttons, responsibilities, stages, transfer checks, money examples and mobile entry points. The existing [order/return/escrow flow chart](../../flows/order-return-escrow.html) includes the new resolution branches.

## Implemented behavior

- Seller proposes responsibility, the resolution and who funds new costs. Buyer-fault fees require buyer agreement; seller sponsorship is supported. Admin reviews disputes and can uphold a seller-fault remedy without an indefinite seller veto.
- Physical return/refund, refund-only, partial refund and exchange have distinct custody and payment behavior. Keeping an item never fabricates physical receipt or restocks it.
- Quotes identify the two hubs and real service legs. Same-hub returns cannot bill inter-hub transport or a second hub. Exchanges reserve a specific replacement variation and stock before collection.
- Buyer-funded costs use either a separate platform deposit or an agreed deduction reserved from genuine available original escrow. Seller/platform deposits are separate. A screenshot never verifies incoming money.
- Original paid services retain their allocation. Merchandise adjustments reverse the corresponding seller proceeds and platform commission. Previously paid-out refund money must first be recovered through verified incoming payments.
- Collection, intake, inter-hub arrival, seller receipt and replacement receipt independently unlock the related return service allocations. Admin records actual outgoing transfers; the entitled recipient accepts receipt before confirmed return service earnings increase.
- Verified unused funding returns to its actual payer. Cancelled refund deductions are released back into original escrow without giving a buyer who kept the purchase an extra refund. Unused refund-recovery repayments reduce the recovery balance.
- Buyer refund accounts and recipient payment accounts are encrypted and access scoped. Evidence/transfer files use protected private-storage routes. Funded buyer accounts require an admin correction review before changing.
- Version checks, locks, unique transfer references, quantity and shipping caps, approval resets, cancellation controls and a 48-hour review target prevent common duplicate-payment and custody bypasses. The target does not automatically reject claims or release money.
- Buyer mobile can review/accept/dispute, verify a private refund account, deposit agreed buyer-funded fees with a receipt, preview proof and confirm a replacement. Rider/inter-hub/logistics mobile can view return remittances and accept receipt; assigned return riders photograph handovers.
- Browser return submission now accepts the distinct ordered/received size and color reasons, correctly captures its uploaded request inside the transaction and creates its agreement/workflow.

## Database

Applied only `2026_11_06_180000_create_return_settlement_ledgers` to `invoizdb`, after a private snapshot of existing return and payment ledgers. This is additive; no historical migration reset was run.

| New table | Purpose |
| --- | --- |
| `return_settlements` | Versioned resolution, approvals, quote totals, encrypted refund account, stock reservation and audit events |
| `return_fund_payments` | Incoming transfer, verification decision, funding purpose and unused-recovery money returned |
| `return_allocations` | Individual service/unused-money allocation, recipient, encrypted receiving account, actual transfer and acceptance |

Verification found all expected columns and the recorded migration. Existing counts were preserved: 2 return requests, 1 return workflow, 51 payment transactions and 56 escrow remittances. New tables were empty immediately after migration. Existing returns already in transit remain on their earlier workflow; no customer approvals, money transfers or custody steps were fabricated.

## Validation

- ReturnSettlementTest, ReturnWorkflowTest and SellerReturnVisibilityTest: **69 passed / 553 assertions**, including legacy seller rejection protection while verified return funding is held.
- BuyerMobileIntegrationTest: **27 passed / 261 assertions**. Updated its old buyer-only login fixture to include the rider lookup table and aligned its pending status assertion with the existing label; no authentication behavior was changed for those fixture repairs.
- Buyer Flutter complete suite: **221 passed**.
- Rider Flutter complete suite: **421 passed**. Updated the return-collection test to submit the required photo multipart upload.
- Focused static analysis of changed buyer and rider files: **no issues**. Full-project analysis still reports older style notices and three unrelated unused imports in rider profile; these do not prevent the successful test/build checks.
- PHP syntax checks passed for changed/new implementation files. Blade compilation and participant render checks passed.
- Browser inspection: buyer, seller, admin, logistics, remittance and guide pages fit widths **320, 390, 768, 1280 and 1920**, with no document horizontal overflow or JavaScript errors in the checked fixtures. Private buyer bank data was absent from seller/logistics renders.

## Operating limits

Transfers remain manual bank/e-wallet operations with explicit admin verification. No gateway transfer, real bank refund, or live customer remittance was executed by development tests. Failed bank transfers and in-transit changes require admin investigation rather than an automatic reversal of custody. Return service earnings have a separate ledger and are available through **Return payments & earnings**; the original earnings ledger is not duplicated.

The UI guide is the detailed operator handoff. Keep the published return policy aligned with actual store practice.

## Android downloads

- Buyer **1.0.1 (build 2)**: release APK built and copied to `public/downloads/invoiz-buyer.apk`.
- Rider **1.0.2 (build 4)**: debug APK built with the existing website APK configuration and copied to `public/downloads/invoiz-rider.apk`. Release packaging could not download an uncached Flutter engine dependency because the Maven host did not resolve; the cached debug build succeeded.
- Both updated APKs match their previous signing certificate and their build-output SHA256 hashes. Previous downloads are backed up privately under `storage/app/backups/apk-before-return-settlements-20261106`.
- Both landing-page links include the published file timestamp to avoid a stale cached download. No installed phone app was silently replaced.
- Started the local Laravel preview at `http://127.0.0.1:8000`; both download endpoints returned **HTTP 200** with the exact new artifact sizes (buyer 54,431,977 bytes; rider 176,100,937 bytes).
- The external header check at `https://invoiz.store/downloads/...` returned **HTTP 530** during this run. The files are updated locally; external download availability could not be confirmed through the public tunnel at that time. The public tunnel must be running for installed apps to reach their configured `invoiz.store` backend.
