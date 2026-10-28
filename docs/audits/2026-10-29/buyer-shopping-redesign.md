# Buyer shopping screens — 29 October 2026

The Flutter shopping journey now uses the existing buyer theme: ivory ground,
deep teal actions, Fraunces headings and Inter reading text. This extends the
home/catalog design to cart, orders, product details and checkout.

| File | Change |
| --- | --- |
| `buyer_mobile/lib/screens/cart_screen.dart` | Replaced the gradient header and translucent rows with the shared account header, readable item panels, full product names, separate prices, 44px quantity controls, visible Remove buttons and a responsive checkout summary. Search, selection, quantity limits, removal and selected checkout IDs remain. |
| `buyer_mobile/lib/screens/orders_screen.dart` | Added the matching heading/search, status filters, product thumbnails, clear totals and order links. Orders refresh after returning from their detail page. Added optional API injection for fixture testing and a mounted check after loading. |
| `buyer_mobile/lib/screens/product_detail_screen.dart` | Larger product name/price, rounded gallery, consistent option/quantity/details/specification/review panels and seller information. Purchase buttons occupy their own layout space, keeping content reachable without a hard-coded bottom overlay. Favorites, chat, galleries, stock/variant validation, Add to Cart and selected Buy Now behavior remain. |
| `buyer_mobile/lib/screens/order_detail_screen.dart` | Matching total/status, vertical progress, parcel records, purchased-item photos, payment summary and order history. Processing/ready orders keep the confirmed stage highlighted. Return, rating, cancellation and delivery-code actions remain; the rating dialog fits small screens and large text. |
| `buyer_mobile/lib/screens/checkout_screen.dart` | Shows the selected items, delivery address, COD, vouchers and server quote, with an always-visible total and order button. The button waits for an address and valid quote. Existing selection, voucher claims and checkout payloads remain. |
| `buyer_mobile/lib/widgets/shopping_ui.dart` | Shared peso formatting, tabular price figures, quantity controls, wrapping value rows, search field and action dock. Uses the existing `intl` dependency. |
| `buyer_mobile/lib/theme.dart` | Explicit Inter on body, button and chip styles, retaining Fraunces headings and the existing palette. |
| `buyer_mobile/lib/models/order.dart` | Reads the server's saved shipping fee. The displayed merchandise discount reconciles item subtotal, net shipping and saved order total, so free-shipping savings are not subtracted twice. Legacy responses without shipping retain their prior discount fallback. No checkout calculation or server data is changed. |
| `buyer_mobile/test/shopping_layout_test.dart` | 27 checks: five screens at 320/390/768px, five large-text cases at 320px, selected checkout IDs/totals, order filtering, four large-text dialogs, and shipping-voucher reconciliation. Real bundled fonts are loaded for screenshot review. |
| `buyer_mobile/test/catalog_refresh_test.dart` | The existing selection-preservation check finds the intended cart row and scrolls the item list, accounting for lazy rendering. |

## Verification

- All 84 Flutter tests pass, including the existing shopping, inbox, security and API-client checks.
- Screenshot review covers all five screens and their scrollable content, plus
  the rating, return, delivery-code and cancellation dialogs. Large text is 150%.
- The analyzer has no errors or warnings; existing informational lint and
  deprecation notices remain.
- Android and web builds succeeded. The buyer web preview at `/buyer-app/`
  was refreshed from the same source.
- Installed over the existing app on the connected TECNO KI7 without clearing
  its data. Visually reviewed Cart, Product, Orders, an existing order's details
  and checkout review. The app remains running on Cart; checked logs contain
  no Flutter errors or crashes. The development build still uses wireless ADB
  reverse port 8000 and the running local Laravel backend.

Rendered fixture screenshots, phone screenshots and test/build/analyzer logs
are retained under the local temporary directory with the
`invoiz-shopping-redesign-20261029` / `invoiz-shopping-*-20261029.log` names.

Tests use mock API fixtures. Real purchases, returns, ratings, cancellations,
voucher claims and outbound messages were not performed. No database migration
or `.env` change is required. The earlier address-form import fix and
store-review test correction remain in the working tree. No commit was created.
