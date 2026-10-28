# Buyer app inbox fixes and QA product cleanup

## Reported failure

Opening Messages or Notifications passed `currentIndex: -1` into a five-destination Material NavigationBar. Flutter asserted before the screen could render. Chat threads used the same invalid index. Earlier component/API tests did not exercise these actual routes.

## Changes

- MainLayout renders the bottom bar only for a valid primary destination. Messages, Notifications, chat threads, product/order details, profile/address forms, and Favorites use their parent/back route without incorrectly highlighting Home.
- Inbox and thread errors have visible retry/refresh actions. Empty inboxes can be refreshed. Real screen tests now open the Home message/bell buttons and follow notification links into conversations and order details.
- Chat replies use a single pending send, keep the draft when sending fails, clear it only after success, and ignore callbacks after disposal. Send failures appear above the composer so an error snackbar does not cover the Send button. The support form validates input and retains failed drafts.
- An open conversation refreshes every ten seconds while foreground/current and refreshes on app resume. It avoids overlapping fetches, preserves a reply that completes during a refresh, and stops its timer/listener when disposed. This is polling, not an OS push-notification implementation.
- Shared inbox events refresh unread badges after reads/replies; the message badge also refreshes when its route returns. Duplicate notification taps/mark-all requests are guarded. Received-message notifications remain in the bounded inbox after reading or replying, rather than disappearing because the buyer sent the newest message. Conversation ownership remains enforced by the API.
- Account now provides a My Favorites entry. Favorites refresh after returning from a product, handle unavailable entries, and prevent duplicate removal requests.
- Recent products are refreshed through a bounded catalog ID filter before rendering. Archived/inactive products cannot return from local recent-product cache or be newly added to favorites/cart.

## QA product cleanup

Archived 14 individually reviewed QA products: IDs 101, 102, 105–116. Their names start with `QA Seller 20261017`, `QA Seller 20261018`, or `QA Seller2 20261018`. One was publicly active: `QA Seller2 20261018 Access Check`; the other thirteen were already inactive.

The operation backed up the full affected product rows, checked identities under a transaction lock, set the products inactive with a soft-delete timestamp, and removed only cart/favorite rows referencing these IDs (there were zero). All 12 historical order-item references were preserved. No accounts or orders were deleted. Public catalog count changed from 66 to 65.

Backup/result files are retained locally under `C:/HeatGuard/logs/buyer-navigation-fixes/`. They are runtime artifacts and are not committed.

## Validation

- Full Flutter suite: **57 tests passed**, including **18 new actual-screen/interaction regression tests**. Pointer hit-test misses are fatal in the new suite.
- Isolated buyer/backend suite: **105 tests, 1,478 assertions passed** using in-memory fixtures. No real purchases or outbound test messages were made.
- Flutter analysis: no errors or warnings; existing informational style/deprecation notices remain.
- PHP syntax: 59 changed PHP files passed.
- Live catalog: 65 products; all 97 referenced image URLs returned images successfully. Catalog validation, guest authorization, and private/traversal checks returned their expected statuses.
- Android debug APK and Flutter web preview built successfully. The APK was installed over wireless ADB without clearing app data, and its on-phone SHA-256 exactly matches the new build. The buyer web preview was rebuilt from the same source.
- Three guest website/browser checks passed with zero page errors.
- After the user signed in, physical-device checks opened Messages, Notifications, all five primary tabs, Favorites, Addresses, Profile, a product detail, and its full-screen photo viewer. The checked screens had zero Flutter runtime errors and no visible connection/assertion error states. The account used for this check had empty message/notification inboxes; populated threads, notification links, sending/retries, and read counts were verified with the actual-screen automated fixtures.
- The live QA search returns no products and the removed product #115 returns HTTP 404. The app was left on Home; real order/payment/account data was not changed by the device checks.

## Limits

These checks cover the repaired screens and the tested shopping/account/API paths. They do not certify every role, external payment/email provider, or real fulfillment workflow. Dependency-upgrade and staging/concurrency limitations from `buyer-security-fixes.md` still apply. End-to-end sends use mock/in-memory recipients to avoid contacting real sellers during testing.
