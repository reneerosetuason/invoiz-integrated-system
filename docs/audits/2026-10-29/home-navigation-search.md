# Home navigation search — 29 October 2026

Moved the home product search into the top navigation where the INVOIZ text
title previously appeared. The logo, message/notification/wishlist actions
and badges remain. Category pills start directly below the navigation;
the previous body search row is removed on Home.

The compact field uses a short “Search” hint and the keyboard's Search action.
Its clear control resets the search while retaining the selected category
and sort. Cart, orders, account and store pages retain their existing titles
and search layouts.

## Files

- `buyer_mobile/lib/screens/home_screen.dart`: hosts the catalog's existing
  search widget in the main navigation.
- `buyer_mobile/lib/widgets/catalog_browser.dart`: optional scaffold builder
  lets a page place the same controller-backed search in its navigation.
  Product loading, pagination and request/filter construction are unchanged.
- `buyer_mobile/lib/widgets/main_layout.dart`: optional search row with the
  existing 32px logo and all three 44px action targets.
- `buyer_mobile/lib/widgets/buyer_scaffold.dart`: optional custom navigation
  content preserves the 60px header and status/notch safe area.
- `buyer_mobile/lib/widgets/buyer_search.dart`: compact native search variant
  fits a 44px row; existing full-width search presentation remains available.
- `buyer_mobile/test/catalog_refresh_test.dart`: exercises the real home
  search, category and sort combination, clearing and notch placement.
- `buyer_mobile/test/shopping_layout_test.dart`: verifies a single search
  inside the navigation, usable field height and reachable header actions.

## Verification

- **170 Flutter tests passed**.
- Analyzer: **zero errors or warnings**; 61 existing informational items.
- Reviewed rendered Home screens at 320px and 360px, with an additional
  enlarged-text review. Existing layout coverage includes 390/430/768/1200px,
  keyboard, safe-area, shopping, messaging and account flows.
- Source comparison confirms that catalog loading/query logic and all header
  action callbacks are unchanged. `git diff --check` passes.
- Local API returned HTTP 200 before the device update.
- Android and web builds succeeded; the local `/buyer-app/` was refreshed.
  Installed over the existing TECNO KI7 app without clearing data, restored
  local API forwarding and launched INVOIZ. Its process was running with no
  Flutter/fatal errors in the checked startup logs. The app was not in the
  foreground at capture time, so no phone screenshot was taken; visual review
  used rendered widget captures.

No backend, model, service, authentication, checkout or environment change was
needed for this layout adjustment. Existing staged changes were retained.
No commit was created.

## Shop follow-up

Applied the same navigation search to `product_list_screen.dart`, covering
Shop and its category/sale browsing variants. Existing category IDs, initial
search, sale filtering, sorting and the selected Shop tab are preserved.
The shared catalog and header implementations did not need further changes.

Added Shop to the existing responsive render coverage in
`test/shopping_layout_test.dart`. All 133 tests in the catalog, inbox-navigation
and shopping-layout suites passed. Reviewed Shop at 320px with standard and
150% text; 430px is also covered. Analyzer: zero errors/warnings, with the same
61 informational items.

Android and web builds succeeded. Refreshed `/buyer-app/`, installed the APK
over the existing TECNO app, restored API forwarding and launched INVOIZ.
Its process was running with zero Flutter/fatal errors in the checked startup
logs. No app data was cleared and no commit was created.

## Individual floating category pills

Updated `buyer_mobile/lib/widgets/catalog_browser.dart`, shared by Home and
Shop, so each category has its own rounded paper/teal surface, hairline border
and soft shadow. The row has no surrounding capsule. Added space inside the
horizontal viewport so the individual shadows remain visible at its edges.
Native buttons retain at least 44px touch targets and selected-state semantics;
labels scale with the device text size. The row stays pinned while products
scroll and remains horizontally scrollable.

Catalog loading, query construction and the category-selection callback are
unchanged. All 133 existing catalog, inbox-navigation and shopping-layout
checks passed. Reviewed Home at 360px and Shop at 320px with standard/150% text;
the existing renders also cover wider screens. Analyzer: zero errors/warnings,
with the same 61 informational items. No new dependencies or backend changes.

Android and web builds succeeded. Refreshed `/buyer-app/` and installed/launched
the update on the paired TECNO without clearing app data. Its process was
running with zero Flutter/fatal errors in the checked startup logs.

## Orders follow-up

Updated `buyer_mobile/lib/screens/orders_screen.dart` to place its existing
controller-backed search in `MainLayout.headerSearch`, using the same compact
`BuyerSearch` as Home and Shop. Removed the duplicate body search row. The
Your orders heading, status filters, order cards, message/notification/wishlist
actions and floating bottom navigation remain. Product-name filtering remains
local and runs while typing or submitting; clearing the search retains the
selected status filter. No API, payment, cancellation or return logic changed.

All nine existing Orders layout/filter checks passed, covering 320/360/390/430/
768/1200px, 150% text, keyboard layout and the To Ship status group. Reviewed
real-font captures at 360px, 320px with enlarged text, and with keyboard insets:
no overflow or awkward header wrapping. Analyzer: zero errors/warnings, with
the same 61 informational items. `git diff --check` passed.

Build logs and renders: `%TEMP%/invoiz-orders-navigation-search-20261029`.

Android build succeeded. Installed over the existing TECNO KI7 app without
clearing data, restored backend forwarding and opened INVOIZ. The phone reached
the local API with HTTP 200 and its app process had zero Flutter/fatal startup
errors. No commit was made.

Web build also succeeded and refreshed the local `/buyer-app/` preview.
