# Return dispatch, destination alternatives and mobile assignments

The live read-only audit found Return #5 / order #88 assigned to Rider #10, the active rider linked to `ailyn.pickup@invoiz.test`. The existing rider return endpoint returned that assignment with HTTP 200. The assignment had not been lost: the installed app exposed returns through a small dashboard shortcut, separately from ordinary deliveries.

## Visible changes

- Rider Home and Deliveries now show a Return jobs card, the assignment count and up to three return numbers. Tap the card for the actual buyer collection or seller-delivery job. Refresh fetches assignments again; returning to the app or refreshing its parent page also refreshes the card.
- Inter-hub driver Home shows Return transfers. These are independent of forward order manifests.
- A single return action is shown as Next step text and an action-specific button. There is no action dropdown. Legacy cases with more than one possible action use radio choices.
- Once pickup is assigned, the hub sees the rider's name and a waiting message. Change assigned rider is collapsed and contains Reassign collection rider.
- Destination logistics hub keeps a real selector. The original order's seller-side origin hub is the recommended default when operational. Suspended hubs and suspended logistics companies are excluded, and an active alternative can be selected.

## Buyer-to-seller custody

1. Seller schedules the authorized return collection. The buyer-side hub assigns an approved online pickup rider from that hub.
2. That rider opens Return jobs, checks the buyer's address, confirms collection and supplies a handover photo. No cash is collected.
3. The collection hub confirms actual intake. Pickup and collection-hub service lines become eligible; eligibility is separate from a paid and accepted remittance.
4. For a cross-hub return, collection staff select the receiving hub and an online driver from the collection company's fleet, and enter the transport reference.
5. The assigned driver opens Return transfers and uses Accept return transfer. Record arrival at destination hub requires a real handover photo. The parcel remains in transfer until the destination hub independently confirms receipt.
6. The selected receiving hub confirms arrival, then assigns its own approved online last-mile rider to deliver to the seller. That rider sees the seller delivery in Return jobs and records the actual handover photo.
7. The seller confirms physical receipt and inspection. The existing agreed refund process continues. Worker payments still require verified delivery escrow, completed work, an admin-recorded remittance and recipient acceptance.

## Destination substitutions and money

The original buyer-side hub remains the collection hub for a buyer-fault return. NorthStar is recommended when it was the seller-side hub on the original order, but is not mandatory. An operational alternative may belong to another company; the collection company's driver still performs the reverse transfer.

Before acceptance, automatic Commission settings price the selected services. During a funded cross-hub dispatch, an alternative receiving hub must preserve the already-agreed service structure and amounts. The code updates only reserved receiving-hub and receiving-rider allocations, clears obsolete recipient details and records the change in agreement history. It refuses to reassign work already eligible/remitted/accepted, or to add inter-hub services to a funded same-hub route. No extra customer charge, duplicate original delivery earning, or new admin commission is created by a substitution.

Driver assignment is saved on the return workflow independently of earnings. A configured zero inter-hub fee therefore does not hide a genuine assigned job or permit dispatch without an online driver.

## Data and permissions

Migration `2026_11_08_230000_add_return_transport_assignment.php` adds nullable driver assignment, acceptance and arrival fields. Existing in-transfer assignments can be backfilled from their recorded driver allocation. A private pre-migration snapshot was saved. Read-only comparison verified the original fields and counts unchanged across return requests, workflows, settlements, payments, allocations, original escrow remittances and payment transactions.

New authenticated endpoints: `GET /api/logistics/courier/returns` and `POST /api/logistics/courier/returns/{returnRequest}`. Drivers see and update only their own assigned in-transfer returns. Arrival photos stay private. Acceptance and arrival are idempotent; arrival cannot stand in for the receiving hub's intake or advance worker earnings.

No live return was reassigned, progressed, reset or paid during verification. No live rider/driver was forced online.

## Validation

- Release verification completed on **9 November 2026**: **209 backend tests / 1,524 assertions passed** across return custody/settlements, seller visibility, original GCash escrow, buyer mobile integration, last-mile routing and all three remittance-acceptance suites (`.tools/return-dispatch-backend-release-check.log`). This supersedes the earlier intermediate backend counts below.
- Initial combined backend regression: 183 passing tests / 1,374 assertions across return, escrow, seller visibility, buyer integration and original routing checks (`.tools/return-dispatch-final-backend.log`).
- Final settlement suite, including two additional zero-fee and earned-work tests: 99 passing tests / 619 assertions (`.tools/return-dispatch-final-settlement.log`).
- Focused rider/courier dashboard and return UI regression: 14 passing tests (`.tools/return-dispatch-rider-regression.log`). Tests use the actual app theme and project Flutter 3.44.5 SDK.
- Final complete rider application suite: **428 tests passed** (`.tools/return-dispatch-rider-all-tests-verified.log`). The first broad run exposed three dashboard mocks that counted the new return endpoint as a dashboard request; those mocks now handle return jobs separately. An extra concurrent rerun was interrupted when the Android build exhausted laptop memory; the final full suite ran successfully after the build with two test workers.
- Analysis of all six changed/new mobile source and test entries: no issues (`.tools/return-dispatch-rider-scoped-analyze.log`). Whole-project analysis still reports 25 existing issues outside the edited screens, including unused profile imports and deprecations; it is not a clean project-wide analyzer result.
- Browser checks: four return-center stages at 320, 390, 768, 1280 and 1920 pixels; assignment text, recommended selection, unavailable-hub fallback and no horizontal overflow/JavaScript errors all passed (`.tools/return-dispatch-ui-check.log`). Screenshots inspected at mobile and laptop widths.
- Blade templates compile and `git diff --check` passes.

User-facing instructions are in [the return/refund guide](../../flows/return-refund-ui-guide.html#logistics).

## APK and actual phone verification

Rider APK **1.0.5 (build 7)** built successfully using a temporary 1,536 MB Gradle heap, one build worker and in-process Kotlin compilation to fit the laptop's memory. No Gradle project configuration was changed for those temporary settings. Signature continuity was checked before publishing; the previous installer was saved privately. The full public download matches SHA-256 `0989b380fadca77c90b1394f420838450e61535e89690e8a1b4bdb6299ddbd7a`, and the public landing page advertises the new version and hash. Buyer APK **1.0.7 (build 8)** and its checksum remain unchanged.

Wireless `adb install -r` succeeded on the connected TECNO KI7 without clearing app data. Installed metadata confirms versionName 1.0.5 / versionCode 7. The app launched and remained running. Its actual Ailyn rider dashboard visibly shows **1 return assignment — Return #5 · Collect from buyer** (`.tools/return-dispatch-phone-ready.png`). No account login was changed and no physical handoff or payment was confirmed. Automated accessibility-tree inspection could not obtain an idle tree; actual dashboard visibility was checked from the screenshot instead. Interactive collection/driver actions were verified with isolated API and widget tests rather than fabricating a live handoff.
