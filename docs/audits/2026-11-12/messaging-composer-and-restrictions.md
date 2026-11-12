# Message composer and supplied role restrictions

## Missing composer

The admin's Ailyn Pickup conversation was eligible for sending. A long inbox stretched the grid's implicit row beyond the fixed-height message panel. The panel clipped the thread footer, hiding both an allowed composer and the read-only explanation for restricted contacts.

Reproduced in a browser with 25 conversations: the composer extended to about 1,891px while the panel ended around 813px. The CSS now bounds the grid row, allows the inbox pane to shrink and scroll internally, and prevents the thread header/footer from shrinking. Phone layouts retain their two stacked rows. The compact dimensions and surrounding navigation remain unchanged.

## Authoritative restrictions

| From / to | Buyer | Seller | Platform admin | Rider | Logistics staff | Inter-hub courier |
| --- | --- | --- | --- | --- | --- | --- |
| Buyer | No self/unrelated buyer chat | Store enquiry / own order | Allowed | Assigned rider | No direct chat | Blocked |
| Seller | Buyers who ordered or sent an enquiry | No self/unrelated seller chat | Allowed | Assigned rider | No direct chat | Blocked |
| Platform admin | Allowed | Allowed | No self chat | Allowed | Allowed | Allowed |
| Rider | Own assignments | Own assignments | Allowed | No self/unrelated rider chat | Authorized hub support | Blocked |
| Logistics staff | Existing delivery-thread reply only | Existing delivery-thread reply only | Allowed | Center-scoped support | Same-company DMs | Own-company couriers |
| Inter-hub courier | Blocked | Blocked | Allowed | Blocked | Own-company logistics | No self/unrelated courier chat |

Staff delivery replies require an existing buyer/seller/rider message, the correct order participant, a real current rider assignment, and authorized delivery scope. Empty threads do not let staff start a customer conversation. Another company's staff cannot read or reply. Staff replies are identified as logistics messages rather than rider messages.

An empty buyer/seller draft no longer counts as an enquiry authorizing unsolicited seller contact. An actual buyer message or order is required. Inactive recipients remain read-only, including existing courier/staff threads. Company managers and the designated global Logistics Admin retain the existing company/global logistics scopes; a company manager's legacy `role=admin` does not grant platform administrator access.

## Verification

- All 36 directed combinations of the six supplied roles tested through authenticated messaging endpoints: permitted pairs can create/reuse an inbox thread, receive `can_send=true`, and send; prohibited pairs cannot start a conversation.
- Additional checks cover scoped staff delivery replies, empty threads, inactive contacts, wrong order participants, cross-company denial, private history, and separate courier/user IDs.
- Messaging, buyer API, and product-chat suites: **52 passed, 556 assertions**.
- Order, return/refund, escrow, payouts and remittance regression suites: **238 passed, 1,812 assertions**.
- **42 browser layout checks** passed. Shared inbox checks use 25 conversations per role at 360px, 768px and 1440px, assert that the composer is inside the panel before typing, send via mocked API responses, and verify a blocked contact's visible read-only explanation.
- PHP/JavaScript syntax, Blade compilation, and whitespace checks passed.

Changes are limited to messaging authorization, message rendering, scoped message-panel CSS, and tests/documentation. No order, return/refund, assignment, payout, or earnings service was changed. Tests use isolated SQLite fixtures and browser API mocks; no test message was sent to a real user. No migration, push, or APK publication was performed.
