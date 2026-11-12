# Messaging design consistency

The shared inbox and older messaging entry points now use the same local Inter font, teal outgoing bubbles, white incoming bubbles, contact avatars, borders, control sizes, and spacing. Each account keeps its existing application shell.

Role styles previously assigned Segoe UI directly to headings and descendants, overriding the inbox's inherited typography. The fix is scoped to `.message-center` and `.mc-legacy`; surrounding role styles and other pages were not rewritten. The shared stylesheet also fixes the older logistics inbox's phone-width overflow.

The font is a WOFF conversion of the existing licensed `buyer_mobile/assets/fonts/Inter.ttf`, served locally with its OFL license. Messaging CSS and the shared render script have file modification version parameters to avoid stale browser copies.

## Changed presentation files

- `resources/views/shared/messages/index.blade.php`
- `resources/views/admin/chat.blade.php`
- `resources/views/buyer/chat.blade.php`
- `resources/views/buyer/messages.blade.php`
- `resources/views/buyer/chat-thread.blade.php`
- `resources/views/seller/chat/index.blade.php`
- `resources/views/seller/chat/show.blade.php`
- `resources/views/logistics/messages/index.blade.php`
- `resources/views/logistics/rider/messages.blade.php`
- `public/assets/shared/css/messages.css`
- `public/assets/shared/js/messages.js` (contact row, avatar, and unread badge markup only)
- `public/assets/shared/fonts/Inter.woff`
- `public/assets/shared/fonts/Inter-OFL.txt`
- `buyer_mobile/lib/screens/chat_screen.dart`
- `rider_mobile/lib/screens/messages/conversations_screen.dart`
- `rider_mobile/lib/screens/messages/messages_screen.dart`

No controller, model, service, route, messaging permission, request payload, order, refund, return, or earnings logic was edited for this design task.

## Verification

- All nine Blade views retain their original form routes/methods, input identities/constraints, CSRF tokens, role conditionals, control IDs, and media-viewer hooks, checked against snapshots taken before the styling work.
- Six shared inbox roles at 360px, 768px, and 1440px: 18 browser checks, including mocked send and contact selection. Computed heading and message fonts match across roles.
- Eight legacy entry points at the same widths: 24 additional screenshot and overflow checks. Total: 42 passing layout checks.
- Buyer, seller, admin, manager, staff, and rider layouts render successfully. Legacy views use synthetic messages without saving them or marking real messages read.
- Messaging authorization and product chat: 22 tests passed, 128 assertions.
- Rider messaging and delivery contact tests: 15 passed.
- Buyer inbox and product chat tests: 6 passed.
- Both mobile messaging analyses: no issues.
- Vite build, Blade compilation, JavaScript syntax, and diff whitespace checks passed.

Browser interaction checks mock API responses; no real users receive test messages. Offline previews use the project's compiled Tailwind utilities and closed Alpine shell panels when the external CDN is unavailable. Screenshot fixtures and contract checks are in ignored `.tools/messaging-verification/` and `.tools/messaging-design-verification/` directories.

Mobile changes are source updates; this task did not rebuild, publish, or install APKs.
