# Mobile density refinement — 29 October 2026

Refined the existing Flutter buyer interface around a 360px-wide phone. The
INVOIZ palette, Fraunces/Inter fonts, features, API calls and business rules are
preserved. This report describes the additional refinement after
`mobile-ui-refinement.md`.

Approximate dimensions in the rendered 360px fixture, at standard text size:

| Element | Previous pass | Refined |
| --- | ---: | ---: |
| Home promotion height | 95px | 60px |
| First product row starts | 345px | 282px |
| Catalog card height | 260px | 224px |
| Product-detail photo height, excluding optional thumbnails | 236px | 190px |

Two complete catalog rows now fit above navigation in the 360 × 844 fixture.
Product names remain 14px and card prices 16px. The layout adapts to enlarged
text instead of scaling the entire interface down.

| File under `buyer_mobile/` | Additional changes |
| --- | --- |
| `lib/widgets/catalog_browser.dart` | Horizontal promotion at normal phone sizes; stacked copy/action with enlarged text. Tighter category/header spacing, wrapping count, readable short sort labels with all full menu options retained, shorter mobile images and recent-product strip. |
| `lib/widgets/product_card.dart` | Stock metadata increased to 12px; existing two-line names, 16px prices and wishlist target retained. |
| `lib/widgets/product_gallery.dart` | Shorter width-based photo area; paging, thumbnails, fullscreen and zoom retained. |
| `lib/widgets/buyer_page.dart` | 22px mobile page headings, 18px panel headings, 12px default panel padding and tighter heading gaps. |
| `lib/screens/product_detail_screen.dart` | 22px mobile product title/price, tighter information spacing and readable 14px descriptions/reviews. |
| `lib/screens/cart_screen.dart` | Price grouped with item identity, tighter rows and separators. Enlarged-text names wrap fully. Selection, variants, quantities, removal, totals and checkout remain. |
| `lib/screens/orders_screen.dart` | Tighter search/filter spacing, card padding, dividers and thumbnails. |
| `lib/screens/order_detail_screen.dart` | Smaller total, tighter header and progress spacing. All delivery/order actions retained. |
| `lib/screens/favorites_screen.dart` | Shorter phone images, aligned names/prices, reduced text-area padding and at least 44px favorite/store-link targets. |
| `lib/screens/seller_store_screen.dart` | Shorter banner, tighter statistics/action spacing, aligned product prices, shorter phone images and at least 44px follow/chat targets. |
| `test/shopping_layout_test.dart` | Added 360px promotion/grid/gallery density assertions, enlarged-text wrapping checks, wishlist target check and long-sort-label render review. |
| `test/catalog_refresh_test.dart` | Settles scrolling before the quantity tap and verifies that the quantity actually updates before checking selection preservation. |

Validation:

- All 152 Flutter tests pass. The final compact sort-label change also passes
  its focused 360px render/interaction check.
- Reviewed rendered Home, cart, product, orders, order detail, checkout,
  Favorites and store screens, including 320/360/430px layouts and 150% text.
  The suite also covers 390/768/1200px shopping layouts and keyboard/dialog cases.
- Fixed enlarged-text promotion word breaks and cart-name truncation found
  during screenshot inspection. No overflow appeared in the reviewed renders.
- Analyzer: zero errors or warnings; 65 existing informational items remain.
- Source comparison against the start of this pass found 78 action callbacks
  and 57 nonvisual methods unchanged. Model/service hashes and palette match.
- Android and web builds succeeded. Refreshed `/buyer-app/` and installed over
  the connected TECNO KI7 app without clearing data.
- Inspected the live Shop catalog with real images on the phone. Its own
  runtime log showed no Flutter or fatal process errors during the check.

Other flows were reviewed with API fixtures, not by submitting real purchases,
messages, account changes or order actions. No commit was created.
