# Checkout receipt upload and compact design

## Current behavior

Buyer GCash checkout now requires an approved seller with an existing receiving QR. Platform escrow receiving settings are not a prerequisite for the buyer to attach a receipt or place a pending order. This applies to the website and buyer mobile API.

The missing upload was caused by `GcashEscrowService::accounts()` reporting the account unavailable when platform settings were empty. Both clients hid their upload controls on that flag; `checkoutSeller()` also rejected valid uploads on the server. Both checks now require the seller QR only.

The escrow safeguards remain: a receipt is private evidence, not confirmation of payment. Seller verification can proceed, but seller escrow deposit requires the platform receiving account. Seller acceptance and dispatch require admin confirmation of the full deposit. Payout and rider remittance acceptance rules are unchanged.

No receiving account details were invented and no live orders or financial state were modified. Read-only local diagnostics found one uploaded seller receiving image and no platform receiving settings. The current receiving image was used only in an ignored local preview fixture.

## Checkout design

The updated request supersedes the earlier large teal receipt treatment. Checkout now uses smaller headings, 13-14 px supporting text, less padding, a narrower content width, white summary and payment panels, outlined method selection, and a compact outlined order action. Teal is limited to text, focus and selection accents. QR images retain their full-size link. Receipt selection has file validation, filename status, and a preview. Mobile uses one column without page overflow.

The explicitly requested design-taste-frontend skill was adapted to this transactional app screen. The user's compact neutral-surface preference takes precedence over its marketing-page rules. Source lint has zero failures and five warnings: existing Inter typography and four outlined interface containers. Actual browser probe/judge reports are retained under `.tools/checkout-taste-probe.json`; they are not claimed as a full skill pass. Reports flag 14 px body text and outlined controls, intentionally chosen for the user's density request, plus shared navigation styling and offline avatar failures. The prior standalone inspector CDP timeout was not retried.

## Validation

- 65 backend tests passed, 468 assertions: private buyer receipt access, multi-store uploads, voucher calculations, checkout without platform settings, seller deposit/acceptance gates, admin review, rider payouts and account deletion. A subsequent focused receipt-suite rerun also verifies mobile API checkout with no platform settings.
- 10 focused Flutter checkout and voucher tests passed. Checkout analysis has no errors or warnings; four existing braces-style informational findings remain.
- Browser interaction checks passed at 1440, 768 and 390 px: receiving image load, required image upload, type validation, filename and preview, COD reset, missing seller QR blocking, failed quote retry, broken QR recovery, no page overflow or script errors.
- PHP/JavaScript syntax, Blade compilation and whitespace checks passed. Local PHP allows 8 MB per upload and 40 MB per POST, above the application's 5 MB per-receipt limit.

Browser previews use isolated SQLite fixture orders and never submit real orders or transfer funds. Screenshots and helper scripts are ignored under `.tools/checkout-redesign-*`. Environment files were not edited.
