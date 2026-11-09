# Orders, returns and earnings

Updated November 9, 2026. Open `/return-center` while signed in to see the cases and actions allowed for your account. Sellers propose resolutions in **Seller → Returns & Refunds**. New cases use automatic Commission service rates and mutual buyer/seller agreement, with seller-deposited delivery escrow and a direct seller refund for new buyer-fault returns. Seller-fault remedies retain their existing separate-payment process. Riders also have **Home / Deliveries → Return jobs** (rider app 1.0.7 build 9), and inter-hub drivers have **Home → Return transfers** in the rider app. See the [current UI guide](return-refund-ui-guide.html).

## 1. Ordering

```mermaid
flowchart TD
    A[Buyer checks out] --> B{Payment method}
    B -->|GCash| C[Buyer scans seller QR and uploads receipt]
    C --> D[Seller verifies receipt and transfers FULL order total to escrow]
    D --> E[Admin verifies actual incoming funds]
    E --> F[Seller accepts, prepares and packs]
    B -->|COD| F
    F --> G[Logistics routes and approves pickup]
    G --> H[Pickup rider collects from seller]
    H --> I[Origin hub receives, scans and sorts]
    I --> J{Another hub needed?}
    J -->|Yes| K[Manifest transfer and destination hub receipt]
    J -->|No| L[Hub assigns and dispatches delivery rider]
    K --> L
    L --> M[Delivery rider collects from hub]
    M --> N[Buyer receives parcel; rider records delivery photo]
    N --> O{Payment method}
    O -->|GCash| P[Collect NO cash; reconcile funded escrow]
    O -->|COD| Q[Record cash collected; reconcile rider remittance]
```

GCash cannot advance just because a receipt was uploaded. Admin must confirm the seller's full escrow deposit. A fully refunded payment cannot restart fulfillment. Each seller parcel has its own custody and allocations.

## 2. Returns and refunds

```mermaid
flowchart TD
    A[Buyer requests return with evidence] --> B[Seller proposes responsibility and remedy]
    B --> C[System calculates fees from Commission]
    C --> D{Buyer accepts exact proposal?}
    D -->|Suggest changes or decline| B
    D -->|Yes| E{New buyer-fault return and refund?}
    E -->|Yes| F[Deduct original seller-funded costs and new delivery]
    F --> G[Seller deposits only new delivery fee; buyer saves receiving account]
    G --> H[Receipt submission authorizes collection; seller schedules pickup]
    H --> I[Buyer-side rider and hub]
    I --> J[Online collection-company driver accepts return transfer]
    J --> J2[Original seller hub recommended; active alternative allowed]
    J2 --> J3[Driver records arrival; receiving hub confirms parcel intake]
    J3 --> K[Online receiving-hub rider delivers; seller confirms physical receipt]
    K --> L[Seller sends exact net refund directly to buyer with proof]
    L --> M[Buyer checks actual receipt and confirms refund]
    G --> N[Admin verifies actual delivery deposit]
    N --> O[Manage Payments releases completed return shares]
    O --> P[Recipients confirm in the same remittance list as orders]
    E -->|No| Q[Seller fault: seller pays delivery separately and refunds full paid amount directly; legacy remedies keep their source]
```

New buyer-fault example: PHP 4,599 item value - PHP 569.90 original seller-funded costs - PHP 160 new return = PHP 3,869.10 direct refund. Original buyer-paid shipping of PHP 50 is credited when calculating original cost recovery. Only PHP 160 is deposited into delivery escrow. Compensation is retained cost recovery, not another payout. No admin customer approval is needed; actual delivery cash must be verified before worker payouts.

Destination selection recommends the original seller-side hub but excludes suspended hubs and companies. A different active receiving hub may be used. After funding, substitutions must preserve the agreed service budget and cannot reassign already earned/paid work. Driver arrival is separate from receiving-hub confirmation, and no cash is collected.

Already accepted/paid contracts keep their recorded terms. The following older funding details apply to those cases and legacy or other remedies. See the current UI guide for the new buyer-fault buttons.


Rules implemented:

- Standard item returns use the configured return window, default 7 days, and the actual merchandise amount paid after voucher allocation. Original completed delivery is not automatically refunded through the standard item-return form.
- An open return freezes unreleased order payouts. Buyer and seller agree to the exact resolution and automatic fee snapshot. Admin verifies transfers, rather than approving normal responsibility or choosing service fees.
- Physical receipt, stock restoration, refund transfer and buyer confirmation are separate events. Damaged stock is not automatically put back on sale. A zero-cash-value return closes without inventing a transfer.
- Repeated receipt/refund actions cannot restore stock or refund money again. Seller ownership is checked per parcel, including older shared orders.
- Unpaid failed COD delivery creates no buyer cash refund. It follows the existing rider → hub → seller return path. Seller confirmation can cancel the order once all its parcels are returned or cancelled.
- A cancelled prepaid order queues a refund review including unused shipping. It requires no second physical return or stock restoration. Payment-only adjustments do not consume purchased return quantities.
- Completed logistics work remains payable. Merchandise refunds reduce the seller's remaining allocation and reverse the applicable merchandise commission. When those funds were already paid out, or are insufficient to cover earned logistics fees, admin must verify recovery funding first.
- Reverse transport uses its own verified return-fee payment and service allocations. Required riders and hubs earn only for independently confirmed work and must accept remittances before earnings are counted. No return fee is deducted from the held refund or seller proceeds.

Support can review a rejected request through the existing Help/complaint process. A complaint decision alone does not transfer money. Receiving-account details and exceptional refund scope still need verification by the responsible staff.

## 3. Money, escrow and earnings

```mermaid
flowchart TD
    A[Buyer payment] --> B{GCash or COD?}
    B -->|GCash| C[Seller receives payment]
    C --> D[Seller remits FULL total; admin verifies escrow deposit]
    B -->|COD| E[Delivery rider records cash actually collected]
    E --> F[Rider retains eligible last-mile fee; remaining cash is reconciled]
    D --> G[GCash held until delivery; COD follows earned milestones]
    F --> G
    G --> H{Open return or dispute?}
    H -->|Yes| I[Hold further payouts; refund and any required recovery first]
    I --> H
    H -->|No| J[Release eligible allocations within remaining funded balance]
    J --> K[Admin: applicable net merchandise commission]
    J --> L[Seller: remaining merchandise proceeds after deductions]
    J --> M[Origin hub and pickup rider: configured fees]
    J --> N[Inter-hub driver and destination hub: eligible completed-route fees]
    J --> O[Last-mile rider: delivery fee]
    K --> P[GCash releases use individual real transfer references]
    L --> P
    M --> P
    N --> P
    O --> P
    P --> Q[Recipient confirms receipt where applicable]
    Q --> R[Rider GCash earnings credited once after acceptance]
```

The configured allocation formula is shared by settlement and escrow. Default fees are origin hub ₱30, pickup rider ₱30 per purchased unit, inter-hub driver ₱20 when eligible, destination hub ₱30 when eligible, and last-mile rider ₱50 unless an applicable delivery fee overrides it. Default platform commission is 10% of net merchandise; category/configured rates may differ. Driver vehicle allowances have a separate existing workflow.

For GCash, every role is paid from escrow with its own recorded transfer reference. COD retains its existing cash and wallet/remittance process; it is excluded from GCash batch releases. Rider GCash earnings appear only after the rider accepts the incoming payout. The release ceiling accounts for actual refunds and verified recovery deposits.

**Transfers remain manual.** The app records private evidence, references, verification and balances; it does not move money through a bank or GCash API. Admin must verify incoming recovery funds and actual outgoing refunds before recording them. Buyer confirmation means the money appeared in the buyer's receiving account.

## Verification and existing records

The implementation and validation notes are in [the return/refund review](../audits/2026-11-05/return-refund-workflow.md).

Existing order #74 was queued for refund review because it was cancelled with a GCash receipt but had no refund case. No stock or money was changed by that repair. Existing order #73 is flagged because it reached confirmed status without a funded escrow deposit; further fulfillment and release are blocked pending seller/admin funding verification. One older closed return has no new payment-proof/confirmation history and is explicitly labelled a legacy record rather than treated as verified receipt.

Orders and return-service payouts share **Manage Payments** and the recipient's regular **Remittances** list. Return rows are labelled by return number and service. Admin confirms the incoming escrow once and releases completed shares; each recipient confirms actual receipt. Return services no longer require their own receiving-account form or outgoing receipt upload. Accepted return pay is included in normal earnings totals/history. The underlying budget limits and source IDs prevent duplicate payouts or spending customer-refund funds on service fees.


## Keep-item refunds (refund only / partial refund)

```mermaid
flowchart TD
 A[Buyer requests refund] --> B[Seller proposes keep-item remedy]
 B --> C[Both agree to refund amount]
 C --> D[Buyer verifies receiving bank account]
 D --> E[Seller transfers directly and uploads receipt]
 E --> F[Buyer views receipt]
 F --> G{Money received?}
 G -->|Yes| H[Buyer confirms and refund closes once]
 G -->|No| I[Buyer explains failed payment or suggests correction]
 I --> J[Buyer and seller exchange messages]
 J --> K[Seller checks bank and corrects receipt]
 K --> F
```

No transport work or refund-funding deposit is created. Receipt submission is pending until the buyer confirms actual receipt. Current receipt identifiers prevent stale confirmation; prior receipt revisions and receiving snapshots remain private. See the [UI guide](return-refund-ui-guide.html#keep-item).


Seller-fault physical returns use the same custody and delivery escrow as buyer-fault returns, with no customer-refund deductions:

```mermaid
flowchart LR
    A[Seller proposes full eligible paid refund] --> B[Buyer agrees and saves receiving account]
    B --> C[Seller deposits configured return delivery fee only]
    C --> D[Existing reverse logistics delivers item back]
    D --> E[Seller confirms physical receipt]
    E --> F[Seller sends full refund directly to buyer with receipt]
    F --> G[Buyer confirms actual money received]
    C --> H[Admin verifies delivery deposit]
    H --> I[Completed workers receive normal remittances]
    I --> J[Each recipient confirms receipt]
```

Example: PHP 4,599 item + PHP 50 original paid shipping = PHP 4,649 direct buyer refund. Seller pays PHP 160 reverse delivery separately when the Commission route totals that amount. Shipping is allocated to this seller and returned quantity; free shipping is not reimbursed again. Original already-earned order shares remain recorded. No customer refund recovery is deposited into platform escrow. Existing funded legacy cases preserve their recorded source.
