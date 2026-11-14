# Private parcel messaging

Delivery conversations now follow the current parcel handler. The assigned pickup rider is available before collection for coordination, as requested. Hub receipt changes the contact to that hub; last-mile assignment alone does not move custody away from the hub. Recorded rider pickup changes the contact to the delivery rider. A dispatched fleet manifest remains with its transport driver until the destination accepts that parcel, including the period after truck arrival. Terminal and archived deliveries cannot generate new outbound delivery chats.

Buyer and seller conversations are independent. A seller cannot read the buyer's delivery conversation, a buyer cannot read the seller's, and hub staff cannot join either party's rider chat. A hub conversation is between one customer and the staff at that handling hub; riders and other hubs cannot read it. Successive handlers receive separate threads and cannot read the previous handler's private history.

Sellers retain independent private messaging with active logistics contacts, including other companies, for partnership enquiries. Buyer–seller store enquiries and platform support remain available. Buyers' operational contacts are parcel-specific, rather than a directory of arbitrary riders or logistics accounts.

`ParcelChat` resolves the holder from delivery custody, fleet manifest receipt, and active return workflow stages. Historical handoff rider IDs and return earnings allocations do not grant contact rights. When several simultaneous partial returns share one delivery and have no unique physical holder, the service does not guess a recipient.

The implementation reuses `logistics_conversations.direct_thread_key` for a private identity containing delivery, customer, handler kind, and handler ID. No new schema or migration is needed. An unambiguous existing private rider thread can be reused. Previous conversations containing logistics staff alongside a rider/customer are retained, labelled as previous shared conversations, and closed for sending. No message history is deleted or copied to a new handler.

Every send checks current authorization. New parcel threads recheck it inside the message transaction after locking the order and delivery, matching the assignment/return custody writers' lock order. The rule also applies to the older rider and logistics send endpoints and attachment access. Current hub unread counts use authorized message records.

The shared Messages page offers explicit parcel contact buttons. Buyer and seller order pages link directly to the current parcel chat. Buyer mobile order details receive the action label from the server. Both mobile chat screens refresh their ability to send while open, so a handoff makes an old conversation read-only.

## Files

- Policy: `app/Support/ParcelChat.php`, `app/Support/MessagingAccess.php`.
- Controllers: `MessagingController`, buyer API `OrderController`, logistics `MessageController`, logistics API `RiderConversationController`.
- Existing model: `app/Models/Logistics/Conversation.php` unread counts.
- Web: shared Messages view/parcel contact partial, buyer and seller order views, shared `messages.js` and `messages.css`.
- Buyer mobile: order model, order detail screen, chat screen, and `parcel_chat_handoff_test.dart`.
- Rider mobile: conversation model, message service, and messages screen.
- Backend tests: `tests/Feature/MessagingAccessTest.php`.

## Validation

- `php artisan test --filter='MessagingAccessTest|BuyerProductChatTest|SecurityHeadersAndChatModerationTest|BuyerMobileIntegrationTest'`: 60 passed, 638 assertions, isolated test fixtures.
- Buyer Flutter: `parcel_chat_handoff_test.dart` and `product_store_chat_test.dart`: 6 passed.
- Rider Flutter: `delivery_message_test.dart`: 1 passed.
- Targeted Dart analysis: no issues in the changed buyer and rider source files and the new buyer widget test.
- Blade compilation and JavaScript syntax checks passed.
- Read-only local database holder audit and shared page rendering succeeded. No real messages were sent during validation.
- Mocked browser transport verified the handoff transition and the current-handler button, with no JavaScript errors or page-width overflow at 1365px and 390px.

Backend restrictions apply to existing clients. The new mobile order buttons and live composer updates require rebuilding/installing the updated app sources; this change does not publish APKs.
