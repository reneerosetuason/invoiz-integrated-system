# Return-fee receipt upload and admin preview

Implemented 8 November 2026.

## What changed

- Split the combined payment section into **Return transport fee — paid by buyer/seller** and **Seller funds for the buyer’s item refund**. Each upload fixes its purpose and expected amount. Admin sees the seller funding explanation and reviews uploaded receipts, rather than a routine upload form pretending admin is the payer.
- The ₱160 in Return #4 is the buyer-funded return transport. The screenshot's ₱4,599 restores original money needed for the item refund after the order proceeds were distributed. They fund different obligations; the buyer does not pay the item price again.
- Web upload accepts the matching screenshot/reference and leaves the payment awaiting verification. Pending payments hide duplicate upload forms. The payer can open their uploaded receipt; other participants cannot read another payer's proof.
- Buyer app 1.0.6 (build 7) has **Upload return-fee receipt**, with reference, image selection, filename/preview and explicit submission confirmation in one dialog. Cancelling selection sends nothing; pending uploads do not show another upload button.
- Admin's pending receipts open expanded with **Open receipt before verifying**, successful-transfer/rejection options, an explanation and actual-bank-check confirmation. The proof uses the existing authenticated in-page lightbox, private/no-store responses and a versioned image URL.

## Verification gate

The migration adds nullable `receipt_viewed_by` and `receipt_viewed_at` to `return_fund_payments`. An authorized admin opening a submitted receipt acknowledges that exact stored image. Success verification requires the same admin to have opened it; rejection can still handle a missing/unreadable receipt. Re-uploading a rejected receipt resets the preview acknowledgement. Viewing or uploading never changes payment status to successful; actual-bank verification retains its existing permissions, order lock, amount/reference checks and activation rules.

The receiving account's account details/instructions remain the platform's configured values. No money, original allocation, return responsibility, service rate or customer agreement was changed by this work.

## Validation

- 134 backend tests passed (1,045 assertions), including private multipart upload, pending status, preview-before-success, rejection/resubmission reset, missing proof, duplicate upload visibility and distinct seller refund-funding UI.
- Six buyer agreement/widget tests passed, including real multipart receipt construction, preview, cancellation at 320 px, pending payment display and the earlier acceptance regression. Analyzer checks passed for the changed screen, uploader and tests.
- Migration applied after a private seven-table snapshot. No live test payment or bank confirmation was submitted.
- Browser checks passed for 29 role/state/guide pages at 320, 390, 768, 1280 and 1920 px. Pending admin receipts open in the in-page lightbox with no new tab; no page overflow or JavaScript errors was found.
- Buyer APK 1.0.6 (build 7) was built, published and installed in place on the connected phone, preserving app data. Android reported the new version/build. A full public download matched SHA-256 `71e6e783dca0abe93ccdc2a074ef9032164537e496a471a22e278f4e8c3d6a23`, and the public landing page showed the matching version and download hash. Receipt submission was tested against isolated fixtures/mock APIs; no live customer payment was fabricated.

See [the user-facing guide](../../flows/return-refund-ui-guide.html#payment) for each field and button.
