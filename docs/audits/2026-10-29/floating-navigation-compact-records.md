# Larger navigation and compact shopping records — 29 October 2026

Refined the existing Cupertino interface with a more prominent top bar, a
floating pill tab bar, and compact cart/order records. INVOIZ colors, fonts,
destinations, badges, account guards, actions and calculations are preserved.

Approximate dimensions at 360px and standard text size:

| Element | Before | After |
| --- | ---: | ---: |
| Top navigation, excluding status bar | 44px | 60px |
| Primary header logo | 26px | 32px |
| Header title | 16px | 18px |
| Tab control height, excluding floating/safe-area spacing | 54px | 62px |
| First cart card in the long-name fixture | 196px | 144px |
| Order card in the fixture | 244px | 156px |

## Changes

Paths below are relative to `buyer_mobile/`.

| File | Changes |
| --- | --- |
| `lib/widgets/buyer_scaffold.dart` | Taller native navigation frame with centered 18px Inter titles and a hairline bottom divider. Page content starts below the full bar, including the notch/status area. |
| `lib/widgets/main_layout.dart` | Larger header logo/action icons. Floating rounded tab bar with 16px phone side insets, a subtle soft shadow, 25px icons, all five destinations and a 540px maximum width on larger screens. Home-indicator and keyboard clearance are retained. |
| `lib/screens/cart_screen.dart` | Compact 48×56 thumbnails; product name, option and unit price grouped together; 44px remove action at the upper right. Item total and quantity controls share the lower row. Removed redundant separators; amounts, availability warnings and selection/quantity/removal/checkout callbacks remain. Selection uses the existing teal palette. |
| `lib/screens/orders_screen.dart` | Compact receipt layout: order/date and status at the top, 48×56 thumbnail with product/option/quantity information, then a 16px total and right-aligned order link. The whole record still opens its existing order details. |
| `test/cupertino_controls_test.dart` | Checks the taller header, floating pill insets/height and notch/home-indicator clearance. |
| `test/shopping_layout_test.dart` | Adds cart/order density limits and remove-action reachability at 360px. Screenshot tests render real soft shadows and restore Flutter's global shadow setting before cleanup. Existing width, enlarged-text, keyboard and behavior checks remain. |

The floating tabs have space reserved below page content, so checkout controls
and list rows are not hidden behind them. Larger text can wrap card content
and controls naturally while normal phones retain compact records. The tab
labels keep native stable sizing and usable touch areas at 320px.

## Verification

- All **158 tests pass**. Shopping renders cover 320/360/390/430/768/1200px;
  enlarged text, keyboard, dialog and navigation cases are included.
- Screenshot review includes Home, cart, orders and enlarged-text cards.
  Totals and quantity/order actions were aligned across the available width.
- Analyzer: zero errors or warnings; existing informational items remain.
- Source comparison found all **19 existing callbacks** and **six API
  expressions** in the edited UI files unchanged. Models, services and
  dependency manifests match the saved baseline. Backend, environment,
  payment rules and stored data were not edited.
- Android and web builds succeeded. `/buyer-app/` was refreshed from this
  source. The Android update was installed over the existing app without
  clearing app data and launched on the connected TECNO KI7.
  Its process was running with no Flutter/fatal error entries in the checked
  startup logs. The app was not in the foreground at capture time, so a phone
  screenshot was skipped; visual review used the rendered widget captures.

An iPhone/macOS environment is not available, so iOS hardware/build validation
remains outstanding. Purchases and order mutations were tested with fixtures;
no real order, payment, message or account changes were submitted.

No commit was created.
