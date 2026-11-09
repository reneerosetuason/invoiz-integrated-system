# Partial refunds use the same direct keep-item flow

Partial refunds use the existing `seller_direct_keep_item` service with buyer/seller acceptance, buyer receiving details, seller-to-buyer transfer proof, receipt review, decline/messages, corrected receipts and explicit buyer confirmation. The recorded refund is the agreed partial amount. Admin approval, return delivery work and platform refund deposits are not required.

The shared page previously labelled partial cases **Refund only**. Its title now correctly says **Partial refund — buyer keeps item**, and shows the partial amount against the full eligible item value. Proposal inputs and backend validation require a positive item refund below the full value; the full-value remedy remains Refund only. Invalid submissions retain the selected remedy and entered amount for correction.

No current live partial-refund cases were found in the read-only audit, so no existing monetary records were changed. The installed buyer client already uses this shared direct-refund flow and renders its agreed amount from the API; no new APK is required for these backend and website changes.

## Checks

- Backend regression selection: **148 passed, 1,159 assertions** across settlements, reverse custody and seller visibility.
- Focused partial flow: **3 passed, 35 assertions**, including amount bounds, negotiation, decline/correction and idempotent completion with only the partial sum recorded. Original escrow rows and stock remain intact; no transport allocations or incoming return deposits are created.
- Buyer app: **13 focused tests passed**, including the smaller displayed amount, no admin/fee controls and the normal receipt-confirmation endpoint.
- Browser checks cover proposal amount validation, buyer and seller receipt/conversation views, correct partial headings and horizontal width constraints at 320, 390, 768, 1280 and 1920 px.

See [the updated UI guide](../../flows/return-refund-ui-guide.html#keep-item).
