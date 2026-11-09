# Direct keep-item refunds and buyer/seller conversation

New refund-only and partial-refund proposals use `seller_direct_keep_item`. The buyer keeps the item, verifies the receiving account, and the seller sends the exact agreed amount directly. No return service allocation, admin proposal approval, platform refund-funding deposit or stock restoration is created.

The seller uploads the outgoing receipt with its actual reference and amount. The reopened buyer agreement shows that image in the lightbox, then offers **Confirm refund received** or **Decline receipt / payment not received**. Declining requires a reason and leaves customer money unrefunded in the ledger. Buyer and seller can exchange messages without resetting the accepted terms. Seller receipt correction is available after a buyer problem report. The buyer may confirm later-arriving money without inventing another payment.

`direct_refund_transfers` stores unique transfer references, explicit receipt revisions, the exact amount and encrypted proof/receiving snapshots. Correcting the same transaction appends a receipt version; historical references remain bound to this case. Default serialization excludes private snapshots. Buyer confirmation and decline carry the reviewed receipt ID/revision, preventing stale actions after a seller correction. Only the buyer can confirm receipt, once; payment totals and purchased quantity are bounded under the original order lock.

Admin and unrelated logistics/rider accounts cannot perform these refund actions or read their private proof/account. Normal Admin escrow distribution for actual delivery services is preserved. Original earned order cuts are not reversed or paid a second time by this external refund.

## Existing Return #6

The user explicitly confirmed its submitted PHP 4,649 refund-recovery receipt was a test with no actual transfer. A guarded transaction backed up the existing records privately, retained the submission as rejected/withdrawn history with an explanation, and changed only the workflow policy and audit history. Both acceptances and the agreed PHP 4,649 remain. No account was invented, no money was recorded as sent, and no buyer confirmation was fabricated. The buyer's existing Verify refund account action unlocks the direct seller step.

The additive transfer-history migration was applied separately. Existing verified platform deposits and already-paid cases are not silently reclassified as seller-to-buyer payments.

## Checks

- Backend regression: 188 tests passed (1,524 assertions) across return settlements, reverse custody, seller visibility, original GCash escrow and buyer API integration. Additional focused tests cover encrypted receipt history, actual buyer confirmation/idempotence, decline and conversation, stale receipt rejection, corrected proof privacy, partial refunds, wrong actors and historical reference reuse.
- Full buyer app suite: 232 tests passed. New tests cover reopening and viewing the exact receipt version, confirmation metadata, decline/messages without another acceptance, and 320/390 px layouts.
- Browser checks passed at 320, 390, 768, 1280 and 1920 px for buyer decline/reply, seller correction and completed state, without horizontal overflow. Mobile and laptop screenshots were reviewed.
- Scoped analysis found no errors; two pre-existing brace style suggestions remain in the API service outside the changed image-query method.

The [updated UI guide](../../flows/return-refund-ui-guide.html#keep-item) lists every field and action.

## Release verification

Buyer APK **1.0.8 (build 9)** was built, checked for signing-key continuity, published and installed in place on the wirelessly connected TECNO phone. Android reports version 1.0.8/build 9. The public HTTPS download matched SHA-256 `37256d80b661536877f018c2f9d8b4e614c331a36c44e4ccd0094b12a885b7bf`; the landing page displayed the new version. The compiled app contains the keep-item policy and receipt-version confirmation code. Rider/logistics 1.0.8/build 10 was preserved.

The final additional keep-item checks passed 4 tests/50 assertions, including ciphertext privacy and rejection of another case's historical reference. Blade compilation and whitespace checks passed. Return #6's workflow now shows refund pending rather than collection pending. Its agreed PHP 4,649 and original acceptances remain; the buyer has not yet saved a receiving account and no actual seller transfer or buyer confirmation was recorded by the agent.
