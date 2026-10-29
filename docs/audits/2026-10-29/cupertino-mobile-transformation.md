# Cupertino mobile transformation — 29 October 2026

Applied the attached iOS-inspired transformation brief to the existing Flutter
buyer app. This pass changes the actual application shell and controls while
retaining the compact layouts, real data, existing navigation guards and
marketplace features.

The app now uses `CupertinoApp`, `CupertinoPageScaffold`,
`CupertinoNavigationBar`, `CupertinoTabBar`, `CupertinoButton`,
`CupertinoSearchTextField`, `CupertinoTextField`, `CupertinoSwitch`,
`CupertinoCheckbox`, `CupertinoAlertDialog`, `CupertinoDialogAction`,
`CupertinoActionSheet`, `CupertinoDatePicker`,
`CupertinoSlidingSegmentedControl` and `CupertinoActivityIndicator`.

Material compatibility remains beneath the native page structure for existing
ScaffoldMessenger feedback, Form infrastructure and localization. Existing
MaterialPageRoute calls use Cupertino transitions. The bottom tab bar invokes
the existing navigation method and authentication guards.

## Files and changes

Paths below are relative to `buyer_mobile/`.

| Files | Changes |
| --- | --- |
| `lib/main.dart` | CupertinoApp shell with the existing navigator key and auth listener; Theme, localization and ScaffoldMessenger compatibility. |
| `lib/theme.dart` | Shared Cupertino theme, Inter controls/navigation, Fraunces editorial text, rounded fallback controls, hairline borders and Cupertino page transitions. Applied the brief's exact muted gray `#6E6E73` and amber `#F0A202`; established teal/ivory colors remain. |
| `lib/widgets/buyer_scaffold.dart` | New shared native page/navigation structure, keyboard resizing and notch/home-indicator spacing. |
| `lib/widgets/buyer_controls.dart` | New native pill buttons, plain/circular actions, disabled/pressed states, switch rows and payment/address selection rows. Minimum button targets are 44px. Existing per-button style overrides remain supported. |
| `lib/widgets/buyer_field.dart` | New Cupertino editing adapter retaining controllers, ownership, input formatters, autofill, password visibility, limits, validation and Form saves. |
| `lib/widgets/buyer_picker.dart` | New native option sheets retaining dropdown values, dependent selections, validation and callbacks. |
| `lib/widgets/buyer_dialog.dart` | New native alerts, explicitly Inter dialog actions, sort sheets and birthday picker with the existing date bounds/cancel behavior. |
| `lib/widgets/buyer_search.dart` | New cream pill search with native text editing and retained submit/search/clear actions. |
| `lib/widgets/main_layout.dart` | Native navigation/tab bars, consistent Cupertino icons and restrained header actions. Cart/inbox badges, all destinations and protected navigation remain. |
| `lib/widgets/buyer_auth.dart`, `lib/widgets/buyer_page.dart` | Native auth page framing; 24px editorial page headings, 20px section headings and subtle panel borders. |
| `lib/widgets/shopping_ui.dart` | Native search and rounded quantity controls; existing formatting and totals retained. |
| `lib/widgets/product_card.dart`, `lib/widgets/product_gallery.dart`, `lib/widgets/product_photo.dart` | Native favorite/gallery/retry controls, native photo viewer and loading indicators. Compact image proportions, real photo paths, paging and zoom remain. |
| `lib/widgets/inbox_status.dart`, `lib/widgets/invoiz_logo.dart` | Consistent icons and native retry control; existing logo asset retained. |
| `lib/screens/login_screen.dart`, `lib/screens/register_screen.dart`, `lib/screens/verify_email_screen.dart` | Native fields/actions, birthday and option sheets; existing registration/PSGC/auth/verification workflows retained. |
| `lib/screens/profile_screen.dart`, `lib/screens/addresses_screen.dart`, `lib/screens/add_address_screen.dart` | Native editing, option controls, default-address switch, confirmation dialogs and save actions. |
| `lib/screens/account_screen.dart` | Grouped native shopping links, rounded personal-details group, consistent logout row. |
| `lib/screens/cart_screen.dart`, `lib/screens/checkout_screen.dart` | Native cart selection, quantity/actions, address/payment selection and pill checkout/place-order actions. All amounts, selected IDs and operations remain. |
| `lib/screens/orders_screen.dart`, `lib/screens/order_detail_screen.dart` | Consistent navigation/icons/actions, native cancel/return/rating/code dialogs and readable enlarged-text dialog layouts. |
| `lib/screens/product_detail_screen.dart`, `lib/screens/favorites_screen.dart`, `lib/screens/seller_store_screen.dart` | Consistent native shopping actions, option/sort sheets and store Products/Reviews segmented control. Existing TabController still loads reviews. |
| `lib/screens/chat_screen.dart`, `lib/screens/notifications_screen.dart` | Native fields, support dialog, send/retry/unread actions and consistent icons. Message drafts, limits and request guards remain. |
| `lib/screens/splash_screen.dart` | Native page scaffold; existing startup delay and destination retained. |
| `test/cupertino_controls_test.dart` | Six new checks for the root shell/feedback, field validation/saving/ownership, controller replacement, picker values/reset, birthday cancellation/confirmation and iPhone safe areas. |
| `test/shopping_layout_test.dart` | Native widget finders, real Cupertino font loading, header-action reachability, sort-sheet capture and the existing phone/large-text/keyboard/dialog checks. Corrected the conversation fixture's message endpoint so populated messages render. |
| `test/buyer_security_test.dart`, `test/catalog_refresh_test.dart`, `test/inbox_navigation_test.dart` | Updated control/icon finders; existing request, payload, navigation, draft and selection assertions retained. |

Pull-to-refresh uses the adaptive indicator. Home and product-list screens
inherit the shared native shell/catalog without needing separate route changes.
Normal phones keep two product columns; enlarged text can use one column.

## Preservation and verification

- Source comparison found all **161 original callbacks/validators** retained
  and **53 API/auth operations** unchanged, including form uploads and requests.
  The new store segment callback delegates to the existing TabController.
- Model/service files and dependency manifests match the saved baseline.
  Backend, database, environment, API configuration and payment logic were not
  edited. No packages, fonts, demo records or app architecture layers were added.
- **158 tests pass.** Focused store/review and 360px header-action checks pass.
- Analyzer: **zero errors or warnings**; existing informational lint/deprecation
  items remain.
- Reviewed actual rendered Home, product, cart, checkout, orders, account,
  profile/auth/address, Favorites/store, message and dialog layouts. Coverage
  includes 320–430px phones, enlarged text, keyboard cases and wider layouts.
- Screenshot inspection caught and corrected dialog action typography and
  unbalanced rating-star wrapping. Native rating controls reflow into balanced
  rows when five 44px targets cannot fit.
- Android and web builds succeeded; `/buyer-app/` was refreshed. Installed over
  the connected TECNO KI7 app without clearing app data, then launched it.
- The app's own process log contained no Flutter/fatal errors after launch.
  A final physical screenshot was not captured because Invoiz was not the
  foreground window; screenshot review used the rendered fixtures described
  above. Device capture checks did not inspect other apps.

Installation and launch checks use the connected Android phone. An iPhone and
macOS iOS build environment are not available here, so an iOS hardware/build
check remains outstanding. Checkout and
order interactions were verified with API fixtures; no real purchases,
payments, messages or account/order mutations were submitted during UI review.

No commit was created.
