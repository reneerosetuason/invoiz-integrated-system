# Mobile website layout review

Scope: responsive website presentation across buyer, seller, marketplace admin, logistics admin/staff and guest pages. No production controller, route, model, database, payment rule or native mobile application was changed.

## Changes

- Added a late-loaded shared CSS/JavaScript presentation layer to the six website shells and the standalone report preview. Existing page-specific styling remains in place.
- Admin navigation becomes an overlay drawer on phones/tablets instead of consuming a permanent 72 px rail. The existing toggle and desktop preference remain; a scrim, Escape closing and keyboard focus handling are added.
- Wide mobile/tablet tables show concise summary fields and View details. Original form controls and action cells stay in their original rows; secondary information remains available in a native dialog. Three-column tables fit their content area. Empty tables, selectable delivery rows and report totals are handled too.
- Fixed form/file-input widths, rigid inline grids, dashboard panels and long order titles. Delivery filters use two compact columns with a full-width search. Logistics headers keep long hub names from pushing profile controls off-screen. Help links wrap on narrow phones.
- Polished the admin account-deletion list/detail presentation without changing filter, restoration or confirmation requirements.
- Added the missing mobile viewport to the report preview. Its screen tables adapt to small widths; print media restores all original data columns and hides mobile View controls. Existing print/save behavior and A4 settings remain.

## Coverage

117 rendered views or states were reviewed: 18 guest, 21 admin, 13 buyer, 42 logistics admin, 3 logistics staff, 17 seller and 3 isolated buyer fixture states. This includes lists, details, create/edit forms, dashboards, account/profile pages, checkout, deletion requests, messaging, settings, reports, inventory, applications and payment screens.

- Initial 390 px sweep: 106 rendered pages, 9 page-level overflows and numerous tables clipped or scrolling inside their containers.
- Full sweep: 114 views at 320/390/768/1440 px, plus three supplemental views at the same widths: 468 measurements with no page-level overflow or script exceptions.
- Final phone/tablet sweep after refinements: 117 views at 320/390/768 px, 351 measurements, zero page-level overflows and zero script exceptions. Tables fit at phone/tablet widths. Remaining out-of-bounds probe entries are intentional product carousels and navigation/filter strips, rather than hidden table actions. Narrow commission-table and Help checks were repeated after their final fixes.
- Original form contracts compared with the presentation helper disabled/enabled on 15 representative pages. Methods, endpoints, encodings, field names/values, disabled flags and required flags match. Original controls were not hidden in detail-only cells.
- Buyer, seller, admin and logistics menus open correctly. Details dialogs close with Escape and restore focus. Admin scrim closing and expanded-state reporting passed.
- Print-media check restores all five original cells of the sampled report row and hides all mobile View buttons. Admin account action rows measured at most 73 px after the compact action layout.
- Blade compilation, JavaScript syntax and whitespace checks passed.

## Validation limits

Live page requests used array sessions and a SQL guard that rejected writes. Several normal GET handlers update address defaults, notifications or message read markers; those requests were deliberately blocked rather than allowed to change live data. Their templates were inspected through read-only rendering copies with those side effects suppressed, direct read-only template data, or existing isolated SQLite fixtures. These preview copies exist only in ignored tools; production methods were not changed. Browser form submissions and polling were intercepted. The dependencies already used by the site were cached locally for accurate offline preview styling.

Hub-transfer and transport-driver detail screens had no sample records for a live browser render. Their templates/shared layout were reviewed; related list/create pages were rendered. Redirects, JSON endpoints, file downloads and access-denied contexts are not counted as separate rendered pages.

Existing automated suites are not fully green:

- Escrow workflow: 16 tests passed, 140 assertions.
- Broader isolated commerce/checkout/deletion run: 82 passed, 7 failed, 534 total assertions. All seven failures were reproduced with this task's shared presentation additions temporarily removed, then the additions were restored. They concern pre-existing cart/variant behavior, a legacy product-button expectation, logistics login authorization, return validation, seller inbox access and payout-request responses; they were not changed in this presentation-only task.
- Older logistics/role/waybill render tests lack the required users/schema in their configured SQLite database. The old view-structure check rejects inline styles already present in unchanged templates. That command reported 17 failed and 3 passed; this is not represented as a passing test suite.

No live form actions, notifications, account changes, order changes, payments or fund transfers were performed. Review snapshots, screenshots, contact sheets and scripts are ignored under `.tools/mobile-*`; they are not committed as customer data. Environment files were not edited.
