# Return-delivery payouts in regular remittances

This earlier implementation is superseded by [Unified order and return remittances](unified-order-return-remittances.md). The current UI uses a single combined list, a simple service release and normal earnings reporting; the separate section and per-service receiving-account/proof steps described below are historical.

The user requested that return-delivery pay also wait in each recipient's ordinary remittance page once admin distributes it.

## Result

The rider and inter-hub driver app remittance pages now embed **Return-delivery remittances**. The website's Hub remittances and Remittances pages include the same recipient-scoped section, also available to hub users opening those pages through their mobile workspace.

Only return service allocations recorded as `remitted` or `accepted` enter this inbox. Reserved or eligible work is not presented as an actual payout. A sent payment stays in **Awaiting your confirmation** until the entitled recipient checks their bank/e-wallet and confirms receipt. The existing locked acceptance operation changes its return allocation to `accepted`; only then does **Confirmed return earnings** increase. Dismissed or failed confirmations leave the payment pending, and repeated confirmation cannot duplicate credit.

The regular pages read the existing `return_allocations` ledger. No second escrow remittance, COD cash handoff, earning row, automatic bank transfer or database migration is created. Previously remitted return payments are included automatically, without replaying a payout. The return funding, verified receiving account, work-completion and admin transfer-proof checks remain in force.

## UI controls

- **Return number, service, amount, transfer reference:** identify the reverse-trip payment separately from the original delivery.
- **Awaiting your confirmation:** total sent but not accepted, across the recipient's matching return payments.
- **Confirmed return earnings:** accepted return-service payments only.
- **View remittance proof:** private image preview using the existing proof route.
- **Accept return remittance received** on web / **Accept remittance received** in the app: explicit actual-receipt confirmation; no optimistic credit.
- **Manage return receiving details:** opens the existing return-payment preparation page for the recipient to save their account before distribution.
- App parent pull-to-refresh and app resume refresh the embedded section. Return pagination is independent of original remittance pagination.

The same inbox handles collection rider, collection hub, inter-hub driver, receiving hub, seller-delivery rider and existing replacement rider services. Recipient and hub/company permissions reuse `ReturnAllocation::canAccept()`; global oversight does not confer hub receipt-confirmation rights. Refunds and seller compensation are excluded from this delivery inbox.

## Validation

New isolated backend scenarios prove released pickup/final-rider fees remain pending until accepted, hub fees appear in the regular hub page, foreign hubs cannot claim them, and a driver sees only its own released transfer fee. Repeated acceptance does not duplicate the original payout ledger or create a second rider earning row. New widget scenarios cover rider and inter-hub regular pages at 320/390 pixels, explicit versus cancelled confirmation and failed acceptance.

Browser checks cover 320, 390, 768, 1280 and 1920 pixels, correct receipt-confirmation forms, private-account non-disclosure and no horizontal overflow or JavaScript errors. User-facing instructions are in the [return/refund guide](../../flows/return-refund-ui-guide.html#remittance-inbox).

- **212 backend tests / 1,558 assertions passed**, including return custody/settlement, original escrow, buyer integration, routing and hub/inter-hub/admin remittance acceptance (`.tools/return-inbox-backend-tests.log`).
- **433 rider app tests passed**, including direct return receipt confirmation in the regular rider and inter-hub screens at 320/390 pixels (`.tools/return-inbox-mobile-all-tests.log`). The existing COD handoff test scrolls to its action after the added return section; original cash handoff behavior remains verified.
- Analysis of the changed remittance source and tests reports **no issues** (`.tools/return-inbox-analyze.log`).
- Browser checks passed at all five widths (`.tools/return-inbox-browser.log`); mobile and laptop section screenshots were inspected.
- Blade templates compile and `git diff --check` passes.

The optional `distributed_only=1` filter on the authenticated return-remittance endpoint exposes only sent/accepted service payments; totals are calculated before pagination. It preserves the full existing return-payment page for receiving-account preparation. The UI uses the same authenticated acceptance/proof endpoints and IDs, so return and original-delivery numeric IDs cannot be confused.

The live read-only baseline showed Return #5's five allocations still `eligible`, with its incoming receipt `submitted`. A follow-up comparison confirmed its return payout records, return deposits and original rider payouts unchanged. Those real transfers were not fabricated or advanced to demonstrate the UI.

## Release and phone update

Rider APK **1.0.6 (build 8)** built with the project Flutter 3.44.5 SDK and temporary low-memory build flags. Publication verified the expected package, increasing build number and signing-certificate continuity, and saved the previous installer privately. The compiled arm64 library contains the new embedded-inbox UI text; the previous APK does not, confirming the release contains the screen changes.

The complete public download matches SHA-256 `8dbbd42354bcd25f6c6aecc5f3e7204cf1c6e5ad6a04338d63ec59664f502d28`. The public landing page advertises that version and checksum. Buyer APK 1.0.7 (build 8) and its checksum were preserved.

Wireless `adb install -r` succeeded on the connected TECNO KI7 without clearing app data. Installed metadata confirms versionName 1.0.6 / versionCode 8, and the app launches. No live fee receipt or financial remittance was accepted on behalf of a recipient.

To open the rider page: Home earnings summary → **Remittances & escrow payouts** → **Return-delivery remittances**. Inter-hub drivers use **View remittances** on Home. Hub users use their regular **Hub remittances** or **Remittances** web page.
