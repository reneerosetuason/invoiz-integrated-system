# Product inquiry cards and store photos — 29 October 2026

The buyer's **Message seller** action on a product now sends a product card
with the photo, name, price and selected option. The card appears in the
buyer's mobile conversation and the seller's web conversation. Tapping the
mobile card opens the product details. The existing inquiry text and normal
chat replies remain available.

The product's **Sold by** panel and the mobile storefront now display the
uploaded store logo instead of always showing initials. Stores without a
logo, or whose image cannot be loaded, retain an initial as a fallback.

## Files

| File | Change |
| --- | --- |
| `app/Http/Controllers/Buyer/Api/ChatController.php` | Accepts optional product/variant IDs, verifies store ownership and availability, calculates the effective sale price plus the selected option adjustment, and saves a catalog-derived card. Conversation/message creation is atomic. Plain store/support messages remain compatible. |
| `app/Models/Message.php` | Adds the optional JSON product-card attribute and array cast. |
| `database/migrations/2026_10_29_000001_add_product_card_to_messages.php` | Adds a nullable JSON column to existing messages. Applied to the local shared database using this migration's path only. Existing records are retained. |
| `app/Http/Controllers/Buyer/Api/ProductController.php` | Includes the public store logo in the lightweight `shop` payload. The storefront API already returned it. |
| `resources/views/partials/chat-product-card.blade.php` | Displays a compact, escaped card with a product link, image/fallback, option and peso price. |
| `resources/views/seller/chat/show.blade.php` | Renders saved cards in the existing seller message bubbles. |
| `buyer_mobile/lib/models/chat.dart` | Parses optional product cards while preserving ordinary text messages. |
| `buyer_mobile/lib/models/product.dart` | Parses the shop logo. |
| `buyer_mobile/lib/screens/chat_screen.dart` | Sends product/variant IDs when supplied, refreshes inbox state, and displays cards in the thread. |
| `buyer_mobile/lib/screens/product_detail_screen.dart` | Supplies the product and selected option to messaging, guards repeated taps during opening, and uses the store photo. |
| `buyer_mobile/lib/screens/seller_store_screen.dart` | Uses the existing storefront logo in its profile avatar. |
| `buyer_mobile/lib/widgets/store_avatar.dart` | Shared circular logo widget using the deployment's storage URL resolver and an initial fallback. |
| `buyer_mobile/lib/widgets/chat_product_card.dart` | Compact, tappable photo/name/price card. At enlarged text sizes the option and amount use the full width to avoid splitting the price. |
| `buyer_mobile/test/product_store_chat_test.dart` | Exercises sending IDs, rendering the returned card, opening the product, both avatar paths, absent logos, and 320px layouts with normal/200% text. Checks that the price stays on one line. |
| `tests/Feature/BuyerProductChatTest.php` | Covers persistence, seller rendering, price snapshots, sales/options, invalid cross-store products/options, existing-thread reuse, plain replies, gallery fallback and both logo API payloads. |

## Verification

- Flutter regression suite: **169 tests passed**. The final enlarged-text
  adjustment also passed all **five focused product/store/chat tests**.
- Flutter analyzer: **zero errors or warnings**; 61 existing informational
  lint items remain.
- Backend product-card and mobile-integration suites: **33 tests passed,
  284 assertions**, against disposable in-memory SQLite fixtures.
- Reviewed rendered cards at 320px with standard and 200% text; names/options
  wrap and the complete price stays together.
- Public API check across three live catalog stores: product-shop and store
  logo values agree. The uploaded logo returned HTTP 200 with an image MIME
  type; two stores without logos retain initials.
- Android and web builds succeeded; the local `/buyer-app/` was refreshed.
  Installed the final APK over the existing TECNO KI7 app without clearing
  data, restored its local API port forwarding, and launched it. The process
  was running with zero Flutter/fatal error entries in the checked startup
  logs. It was not in the foreground at capture time, so no phone screenshot
  was taken; visual checks used rendered widget captures.

Card name, price and option are snapshots from the server's catalog; callers
cannot choose their own card price/name or attach a different store's product.
Image references point to the uploaded file. Missing/deleted files use the
photo fallback. Older text-only inquiries are not backfilled with cards.

No real message, order, payment or account change was submitted during testing.
No environment credentials, routes, cart/checkout logic or logistics code were
changed for this fix. No commit was created.
