# Buyer mobile design and function update — 28 October 2026

The Flutter app in `buyer_mobile` now follows the website's ivory, deep teal and soft mist palette and uses the same existing logo. Shared theme changes reach forms, account pages, orders and store pages. Home, catalog, navigation, product cards and photo galleries received the main layout changes.

## What changed

- Replaced the orange home screen, oversized decorative banner and floating navigation with a compact branded header, search, category chips, product grid and a fixed navigation bar. Content no longer sits underneath the navigation.
- Home and Shop share `widgets/catalog_browser.dart`. Search, category and sorting are combined in server requests. Pagination exposes the rest of the collection through Load more, and stale responses cannot overwrite a newer selection. Loading, empty, failure and retry states are explicit.
- Removed the nonfunctional Shop the edit button and superficial local-only filters. Explore sale opens the running-sale catalog. Vouchers remain collectable on the sale page; wishlist buttons are available on catalog cards.
- Catalog wishlist state is supplied by the API and scoped to the current buyer. Guests do not receive another user's saved state.
- Public photos use a common loading/retry component. URLs from old localhost/LAN deployments are rebased to the current API host; valid external CDNs are preserved. Malformed or unsafe local paths are rejected.
- Galleries retain the cover photo, resolve both legacy photo-path columns, order the remaining photos, remove empty entries and avoid duplicates. Product photos open in a full-screen viewer with swipe, previous/next controls and pinch zoom.
- Recently viewed history is now scoped to the API installation. Old global history remains stored, but no longer inserts stale product pictures from another backend into this app. Newly viewed products keep the current image and sale metadata.
- Product availability follows the selected active variant rather than only the base product's stock. Sold-out options are disabled, the first available variant is selected and quantity is capped by its stock and the existing 200-unit limit.
- Buy Now stops if adding fails. On success it opens checkout for the matching cart line rather than opening the whole cart.
- Cart quantity changes preserve the buyer's selected/unselected lines. Successful cart and checkout changes refresh the shared cart badge; the badge counts units.

## Important files

| File | Purpose |
| --- | --- |
| `buyer_mobile/lib/theme.dart` | Website-aligned colors, typography scale, fields, buttons and navigation. |
| `buyer_mobile/lib/widgets/main_layout.dart` | Shared app bar, navigation, authentication gates and cart badge. |
| `buyer_mobile/lib/widgets/catalog_browser.dart` | Search, categories, sort, paging, sale vouchers and recently viewed. |
| `buyer_mobile/lib/widgets/product_card.dart` | Catalog cards, prices, availability and wishlist actions. |
| `buyer_mobile/lib/widgets/product_photo.dart` | Common photo rendering, loading and retry states. |
| `buyer_mobile/lib/widgets/product_gallery.dart` | Gallery, thumbnails and full-screen swipe/zoom viewer. |
| `buyer_mobile/lib/config.dart` | Media URL resolution against the configured backend. |
| `buyer_mobile/lib/models/product.dart` | Gallery normalization and available variant stock. |
| `buyer_mobile/lib/screens/product_detail_screen.dart` | Quantity, variants, add-to-cart and selected Buy Now checkout. |
| `buyer_mobile/lib/screens/cart_screen.dart` | Selection preservation and quantity validation. |
| `buyer_mobile/lib/services/buyer_updates.dart` | Cart change notifications for the badge. |
| `app/Http/Controllers/Buyer/Api/ProductController.php` | Shared gallery normalization and buyer-scoped wishlist state. |

## Verification

- 24 Flutter tests passed, including new media URL, gallery, recent-history, combined-filter, paging, retry, request-race, failed Buy Now, variant-stock and cart-selection checks. Catalog layouts passed at 320, 390 and 768px.
- 25 buyer mobile API tests passed with 219 assertions. New tests cover legacy gallery columns and cover ordering, plus wishlist visibility across guests and two buyers. All backend tests use an isolated in-memory SQLite fixture with fake mail.
- The analyzer reports no errors or warnings; 49 informational style/deprecation notices remain. PHP syntax and git whitespace checks pass.
- Web build succeeded and refreshed `/buyer-app/`. Android build succeeded and the app was updated and launched on the connected TECNO KI7 with its data retained.
- On the phone, the home screen, product page and full-screen uploaded photo were visually checked. The app remained foregrounded and no Flutter runtime error appeared in the checked logs.
- All 97 existing catalog photo URLs returned HTTP 200 with image content types. Files missing from the physical public copy already work through the existing storage fallback. No storage folder or upload was replaced. Products without an uploaded photo retain an explicit placeholder.

The phone uses the existing wireless ADB connection and reverse port 8000 to the localhost Laravel backend. Keep that connection and the backend running for this development build. No new dependencies, database migrations, production orders, customer cart changes or emails were required. Only public catalog screens were exercised on the actual phone; stock, cart, checkout and account ownership mutations were tested in isolation.

Local backups, manifests, test/build logs and phone screenshots are in `C:/HeatGuard/logs/buyer-mobile-refresh/`.
