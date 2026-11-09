# Buyer-fault returns: seller direct refund and delivery escrow

Implemented November 8, 2026. The [UI guide](../../flows/return-refund-ui-guide.html) explains the visible fields, tables and buttons. The [process diagrams](../../flows/order-return-escrow.md) include the new path.

## Confirmed commercial terms

- Scope: new buyer-fault **Return and refund** proposals. Seller-fault, exchange, keep-item and existing paid agreements keep their existing behavior.
- Buyer and seller agree to the exact proposal version. No site-admin responsibility decision or manual return-service quote is required.
- Original seller compensation is recorded original commission and logistics costs attributable to the returned quantity, **less original buyer-paid shipping credit**. It is retained cost recovery, not a new payable allocation.
- New delivery prices come automatically from Commission settings and the reverse of the original hubs. A different hub/price cannot be silently substituted after consent.
- Seller deposits only the new return-delivery budget into platform escrow, then sends the net refund directly to the buyer after physical return receipt.
- Site admin verifies the incoming delivery money and distributes eligible delivery services in **Finance → Return delivery escrow**. Only actual verified funds can pay completed work. Each recipient accepts the remittance before it becomes confirmed return-service earnings.

Confirmed example:

| Item | PHP |
| --- | ---: |
| Eligible item value | 4,599.00 |
| Original commission/logistics allocations | 619.90 |
| Less original buyer-funded shipping credit | 50.00 |
| Original seller-funded costs retained | 569.90 |
| New reverse delivery | 160.00 |
| **Seller sends directly to buyer** | **3,869.10** |
| Seller deposits to delivery escrow | 160.00 |

The original seller net was PHP 4,029.10. The direct refund and new delivery deposit total that amount. No second original-shipping deduction, compensation payout or return admin commission is created.

## UI and authorization

1. Seller selects responsibility/remedy and submits the proposal. The server rejects manual compensation and uses the original ledger and configured new service rates.
2. Buyer reviews original cost recovery, original-shipping credit, new services and net refund. Buyer accepts, declines or suggests changes. Changes require a revised proposal and fresh acceptance.
3. Buyer saves receiving details. They remain encrypted and are shared with this seller only after mutual agreement; the new admin delivery page does not need this customer's refund account.
4. Seller transfers the delivery amount to the platform, enters the reference and uploads the actual receipt. The buyer has no separate return-fee upload in this policy.
5. Receipt submission plus mutual agreement and receiving details authorizes collection. This is recorded separately from bank-verified funding. Rejection pauses further custody until corrected; resubmission does not reset an already-moving parcel.
6. Seller schedules buyer pickup in Philippine time. Logistics handles independent handoffs and records actual parcel photos.
7. Seller confirms physical receipt/inspection. Restocking is optional and requires a resellable item.
8. Seller sends the exact net refund, uploads proof and records the outgoing reference. Admin and buyer cannot impersonate the seller's payment action.
9. Buyer previews the proof, checks actual account receipt and confirms. Only that confirmation records the customer's refunded total and closes the case. Retries cannot duplicate it.
10. Admin opens the private delivery receipt before recording actual incoming-bank verification. This can occur independently of customer refund completion. Payout controls remain hidden/blocked until actual verified funds and recipient receiving details are available.
11. Completed return-service allocations are remitted with individual actual outgoing references/proofs. Riders, drivers and hubs confirm receipt; only accepted allocations count as confirmed return-service earnings.

The reverse services are buyer-side pickup rider, buyer-side collection hub, that collection company's inter-hub driver, seller-side receiving hub and seller-side last-mile rider. For the supplied route, AILYN HUB becomes the return origin and NorthStar becomes the return destination. Same-hub routes omit inter-hub and second-hub work.

Reported issues go to the other party. The reporting buyer/seller can resolve their issue without an admin customer decision. Actual terms lock when money/custody begins. A pending screenshot never counts as verified money or permits a payout.

## Ledger and schema

Migration: `2026_11_08_220000_add_seller_direct_buyer_fault_returns.php`.

| Table / field | Purpose |
| --- | --- |
| `return_settlements.policy_version` | Existing contracts default to 1; new cases and untouched drafts adopt 2. |
| `workflow_policy = buyer_fault_direct` | Identifies this direct seller-refund path without rewriting existing contracts. |
| `cost_snapshot` | Original role costs, per-return shares, quantity, original buyer-shipping credit and resulting seller-funded recovery. |
| `deduction_note` | Server-generated explanation of the agreed deductions and net refund. |
| `collection_authorized_at` | Records customer/custody authorization independently of verified money. |
| `funded_at` | Remains a bank-verified delivery-funding gate for actual payouts. |
| `return_fund_payments` | Only `return_budget` is accepted for the new policy. Private receipt preview acknowledgement and actual-bank review remain required before success. |
| `return_allocations` | Only the five actual return-delivery roles. No duplicate seller compensation or new admin commission. |
| `return_workflows.refund_source` | `seller_direct` for the new external refund; existing rows remain `platform_escrow`. |
| `refund_sender_user_id` | Records the seller who reported the actual direct transfer. |
| `return_requests` / payment transaction | Customer refund status/total updates once on buyer receipt confirmation. |
| Original `escrow_remittances` | Original completed commission/logistics remain earned. The external refund does not debit original platform cash a second time. |

Original costs must come from a recorded, reconciled original payment, not an unreconciled COD status. Partial returns allocate costs/credit proportionally and cap recovery across other accepted returns. A changed original cost snapshot requires a fresh proposal. Deductions cannot create a negative refund.

## Preservation and live checks

A private seven-table backup was saved under `storage/app/backups` before applying the migration. A read-only comparison confirmed all original fields and row counts remained unchanged:

- 1 settlement; 2 funding payments; 5 return allocations; 2 workflows; 3 return requests.
- 84 original escrow remittances; 56 payment transactions.
- Return #4 stays policy version 1 / `direct`, with its existing verified buyer PHP 160 and seller PHP 4,599 deposits. It was not reset, repriced, refunded or charged again.
- Actual live admin delivery escrow, existing buyer agreement and existing seller agreement each rendered successfully under a database write prohibition.

Test this new policy with a fresh return rather than treating Return #4's already-paid contract as a new deduction agreement.

## Validation and release

- **166 backend tests / 1,282 assertions passed**, covering returns, original escrow, web seller visibility and buyer mobile integration (`.tools/buyer-fault-return-final-tests.log`). MySQL JSON object-key normalization is explicitly covered so equivalent stored snapshots do not block buyer acceptance.
- All **228 buyer Flutter tests passed**. Focused return widgets cover no separate buyer fee, the exact deductions, private previews, saved acceptance and actual refund receipt confirmation.
- Flutter analysis of changed return screens, receipt dialog and tests passed with no issues.
- **41 role/state/guide pages at 320, 390, 768, 1280 and 1920 px** passed overflow, role-action and image-lightbox checks. Representative mobile and laptop screenshots were inspected.
- A targeted render test also checks that admin cannot see a return remittance transfer form before verified delivery cash is available.
- Blade views compiled successfully. The public admin route is registered.
- Buyer APK **1.0.7 (build 8)** built, passed signature continuity checks and was published to the landing-page download. The full public download matches SHA-256 `c5c3df6b0531e941aeffe5a05e67c88dd3e62eb95a220395a80ca5486d50f12b`; the public landing page advertises this version/hash.
- Rider APK remains **1.0.4 (build 6)**. Its existing custody/remittance APIs keep the same contract.
- The user's TECNO KI7 connected through wireless debugging after publication. An in-place `adb install -r` completed successfully; installed package metadata confirms **1.0.7 / versionCode 8** and the application launched successfully. App data was not cleared.

All financial write tests used isolated fixtures. No live transfer, receipt verification, user refund, new return or worker payout was fabricated. Transfers are still performed in the user's bank/GCash application; Invoiz records evidence and the responsible receiving confirmations.
