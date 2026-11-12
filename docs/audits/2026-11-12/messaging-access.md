# Messaging access and shared inbox

Date: 2026-11-12

## Result

The web Messages navigation now opens `/message-center` for all user roles. The inbox reads both existing marketplace conversations and logistics conversations; no message history was deleted, moved between tables, or copied into a second message store. Buyer and rider mobile inboxes use the same source-qualified thread keys, including Hub Transport Driver authentication through Sanctum.

The old messaging routes remain available. Their recipient and logistics thread checks use the shared authorization rules, so bypassing the contact picker does not grant additional access.

## Contact rules

| Account | Can initiate contact with |
| --- | --- |
| Buyer | Approved stores, platform support, delivery/return riders with a real assignment |
| Seller | Platform support, buyers with an existing enquiry or order, assigned delivery/return riders |
| Delivery rider | Platform support, authorized logistics staff for the rider's hub/company, the buyers and sellers associated with their delivery/return assignments |
| Logistics staff | Platform support, global logistics administration, staff/managers within their company, riders within their authorized hub scope, hub drivers within their company |
| Company manager | Platform support, global logistics administration, staff/riders/drivers within their company |
| Global Logistics Admin | Platform support and logistics personnel across companies |
| Platform Administrator | Active accounts for platform support; this does not grant access to other people's private direct threads |
| Hub Transport Driver | Platform support and authorized logistics personnel within their company |

Self-messaging and inactive/suspended recipients are blocked. Two ordinary buyers cannot initiate chat with one another. Sellers cannot initiate unrelated seller/buyer contacts. A legacy `role=admin` company manager is not treated as a Platform Administrator; the actual `is_admin` flag controls platform support.

Existing private history stays readable by its participants. Sending is checked again against current account and assignment eligibility. Staff can handle shared rider-support conversations within their scope and reply in existing delivery coordination threads for deliveries they are authorized to handle. They cannot initiate buyer/seller DMs or open unrelated customer threads. Hub driver direct threads belong to that driver and the addressed logistics/support user. Driver IDs and user IDs occupy separate identity namespaces, even when their numeric IDs coincide.

## Messaging fixes

- Marketplace and logistics threads use distinct keys, such as `marketplace:7` and `logistics:7`, rather than conflating equal numeric IDs.
- Staff/customer conversations appear in both parties' supported inboxes.
- Generic mobile support requests are addressed to a real Platform Administrator rather than creating an unassigned conversation.
- Rider delivery seller threads use the particular delivery's seller, avoiding exposure to every seller in a multi-store order.
- Opening a support chat as a rider no longer clears the staff-side unread state.
- Polling handles initially empty conversations, updates read/edit/delete states in rider thread views, and retains previously loaded history.
- Buyer and web inboxes can load earlier messages; controls reflect read-only eligibility.
- New logistics attachments are stored privately and served through authenticated participant checks with existing content validation. Legacy public-disk attachments were not relocated; existing public storage URLs are not revoked by this change.
- Mobile changes are source changes. This task did not publish or install new APKs; installed apps need a new build for the updated inbox and authenticated attachment viewer.

## Scope

New runtime files:

- `app/Support/MessagingAccess.php`
- `app/Http/Controllers/MessagingController.php`
- `routes/shared/messaging.php`
- `resources/views/shared/messages/index.blade.php`
- `public/assets/shared/css/messages.css`
- `public/assets/shared/js/messages.js`

Existing messaging controllers updated:

- `app/Http/Controllers/Admin/ChatController.php`
- `app/Http/Controllers/Buyer/BuyerChatController.php`
- `app/Http/Controllers/Buyer/Api/ChatController.php`
- `app/Http/Controllers/Seller/ChatController.php`
- `app/Http/Controllers/Logistics/MessageController.php`
- `app/Http/Controllers/Logistics/RiderMessageController.php`
- `app/Http/Controllers/Logistics/Api/RiderMessageController.php`
- `app/Http/Controllers/Logistics/Api/RiderConversationController.php`

Other integration points:

- `app/Models/Logistics/Conversation.php` (authorized unread scope)
- `app/Support/ChatBridge.php` (reuse existing direct thread when attaching legacy messages)
- `routes/api.php`, `routes/web.php`, `routes/buyer/web.php` (messaging routes and chat-only guards)
- `resources/views/shared/navigation/navbar.blade.php`, `sidebar.blade.php` (Messages links and unread count)
- `buyer_mobile/lib/models/chat.dart`, `buyer_mobile/lib/screens/chat_screen.dart`
- `rider_mobile/lib/models/conversation.dart`, `rider_mobile/lib/services/message_service.dart`
- `rider_mobile/lib/screens/messages/conversations_screen.dart`, `messages_screen.dart`

Tests: `tests/Feature/MessagingAccessTest.php`, `buyer_mobile/test/messaging_inbox_test.dart`, and `rider_mobile/test/messaging_inbox_test.dart`.

No migrations were required. No order, return/refund, earnings, checkout, assignment, or remittance service was changed. Existing uncommitted return UI, cleanup, and branch compatibility work remains intact. No commit, push, or message to a real user was performed.

## Verification

| Check | Result |
| --- | --- |
| Messaging authorization, buyer API and product chat | 49 tests passed; 389 assertions |
| Order, return/refund, escrow, payouts and remittance regression suites | 238 tests passed; 1,812 assertions |
| Rider messaging and delivery contact widgets/services | 15 tests passed |
| Buyer product chat and source-qualified inbox routing | 6 tests passed |
| Both mobile messaging analyses | No issues |
| Six real web role layouts | Rendered successfully |
| Buyer/seller/admin/staff/manager/rider at 360, 768 and 1440px | 18 browser checks; mocked send/contact actions and no messaging content overflow |
| Vite build, Blade compilation, PHP syntax, JavaScript syntax | Passed |
| Shared backend connection | Passed; 92 models |
| Production financial data | All row counts and SHA-256 hashes in the 13 previously snapshotted financial/order/return tables unchanged |

Backend tests use isolated SQLite fixtures. Browser interaction checks mock API responses, so no test messages reach real users. Logistics preview screenshots use the project's locally compiled Tailwind utilities and closed Alpine panels because the external CDN was unavailable in the preview; surrounding shell behavior was not rewritten.

Detailed logs are in the temporary `invoiz-messaging-20261112` directory. Ignored local HTML previews, screenshots, and browser results are under `.tools/messaging-verification/`.
