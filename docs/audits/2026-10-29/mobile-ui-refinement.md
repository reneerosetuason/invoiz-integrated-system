# Mobile UI refinement — 29 October 2026

Applied the attached mobile refinement brief to the existing Flutter buyer
application. The interface remains INVOIZ: teal, ivory, Fraunces and Inter.
This pass changes presentation only.

## Design changes and files

| Files under `buyer_mobile/` | Visual changes |
| --- | --- |
| `lib/theme.dart` | Restrained 24–26px page headings, compact navigation, 44–48px controls, less pill-shaped primary buttons, tighter fields/chips and existing semantic colors. |
| `lib/widgets/main_layout.dart` | Compact branded primary header; secondary pages prioritize their title. Crowded narrow headers retain all actions on a second row. Bottom navigation stays mounted while hidden behind an open keyboard. Routes and handlers are retained. |
| `lib/widgets/catalog_browser.dart` | Short promotion with the existing sale link and COD information; smaller proportional grid cards and recent-product strip. Normal phones retain two columns. Large text can use one column; wider layouts retain more columns. |
| `lib/widgets/product_card.dart` | Two-line Inter names, restrained prices, aligned text areas, compact metadata and unchanged photo/favorite/product actions. |
| `lib/widgets/product_gallery.dart` | Gallery height follows available width and is capped at 300px; compact thumbnails. Image paging, zoom, viewer and navigation handlers remain. |
| `lib/widgets/product_photo.dart` | Cream photo loading/fallback ground. Loading and retry logic is unchanged. |
| `lib/widgets/buyer_page.dart` | Smaller gaps and panel padding; existing heading/body fonts and surfaces. |
| `lib/widgets/shopping_ui.dart` | Less space around totals and purchase actions; existing price formatting, stepper callbacks and search behavior. |
| `lib/screens/cart_screen.dart` | Compact photo/name rows and totals, shorter checkout dock, and a layout that keeps the search results accessible with the keyboard open. |
| `lib/screens/orders_screen.dart` | Tighter filters, order cards, thumbnails and separators. |
| `lib/screens/order_detail_screen.dart` | Reduced space between existing order sections and a restrained total size. |
| `lib/screens/product_detail_screen.dart` | More compact prices, section gaps and content around the proportionate gallery. |
| `lib/screens/checkout_screen.dart` | Tighter existing sections, item review and total/action area. |
| `lib/screens/favorites_screen.dart` | Responsive card proportions, Inter titles and room for large text. Product, store and removal actions are retained. |
| `lib/screens/seller_store_screen.dart` | Compact store header and product grid; statistics use two rows with large text. The header gives space to search results while the keyboard is open. Follow/chat/sort/paging handlers remain. |
| `lib/screens/notifications_screen.dart` | Unread count and mark-all action wrap within narrow widths. |
| `lib/screens/account_screen.dart` | More compact account identity and section headings. |
| `lib/screens/profile_screen.dart` | Smaller avatar and tighter form spacing. |
| `lib/screens/addresses_screen.dart`, `lib/screens/add_address_screen.dart` | Consistent phone gutters and reduced unnecessary page padding. |
| `test/shopping_layout_test.dart` | Expanded responsive, density, large-text and keyboard checks using mock API fixtures and real bundled fonts. |
| `test/buyer_security_test.dart`, `test/catalog_refresh_test.dart` | Existing interaction tests explicitly select the intended scrollable and settle scrolling before tapping. Their payload/behavior assertions remain. |

The composition follows the brief's practical reading of Apple's
[UI design guidance](https://developer.apple.com/design/tips/) and
[layout guidance](https://developer.apple.com/design/human-interface-guidelines/layout):
clear hierarchy, fitting the screen, usable touch targets and adaptable text.
Existing Material widgets were restyled rather than replacing working controls
or adding platform/font dependencies.

## Function preservation

- Compared source against a saved baseline: **98 nonvisual methods** and
  **121 action callbacks/validators** have identical normalized source.
- Model and service files match the baseline hashes. Backend controllers,
  routes, database files, environment, API configuration, dependency manifest,
  payment logic and existing state/validation rules were not modified.
- The source comparison supplements the regression suite; it is not a claim
  that every external integration was executed.

## Verification

- **152 Flutter tests pass.** Shopping and home layouts cover 320, 360, 390,
  430, 768 and 1200px. Secondary screens cover 320/430px and 150% text at 320px.
- Keyboard cases cover search, cart, orders, store, login, registration,
  profile, verification, address entry and conversation on a short phone.
  Rating and return dialogs also receive keyboard checks.
- Screenshot review includes populated fixture screens, scrollable content,
  large text, dialogs and keyboard layouts.
- `flutter analyze`: no errors or warnings; informational legacy lint and
  deprecation notices remain.
- Android and web builds succeeded. `/buyer-app/` was refreshed from the same
  source. Installed over the existing app on the connected TECNO KI7 without
  clearing its data, then opened Invoiz.
- Physical screenshot review confirmed the compact Home promotion and populated
  product grid. Other screens were reviewed through rendered fixtures; further
  remote taps were stopped when another application became foregrounded.
- Final check: Invoiz is running and foregrounded. Its checked runtime logs
  contain no Flutter errors or crashes.

The development phone uses the existing wireless ADB connection and reverse
port 8000 to the running Laravel backend. Keep that connection and backend
running. The app is ready to open; browse Home, Cart, Orders, a product and
Account to review the styling. No account or order action needs to be submitted.

Source backups, the contract comparison, rendered fixtures and build/test logs
are stored in the local temporary directory under
`invoiz-ui-refinement-20261029` and `invoiz-ui-refinement-*-20261029.log`.

No real purchase, voucher claim, outbound message, cancellation, return,
rating, registration or profile/address update is needed for this visual pass.
File uploads, SMTP, external services and native iOS were not retested; the
existing supplied mobile targets are Android and web. No commit was created.
