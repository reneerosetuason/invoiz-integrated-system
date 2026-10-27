# Compact logistics pages — October 27, 2026

The logistics admin and staff accounts now use a smaller, more consistent scale while retaining the existing ivory, teal, Fraunces and Inter theme. This change targets authenticated operational pages.

## Presentation changes

- Page headings are 28px on desktop and 24px on phones. Panel headings, financial figures, dashboard totals, panel padding and row spacing are reduced.
- Muted teal summary tiles and table headings distinguish information without adding a broad new color palette. Record names remain text links; row actions have consistent compact controls.
- Centers, staff, service areas, applications, riders and reports have action-oriented descriptions. Pickup requests say “Needs center assignment” rather than “Needs Routing.” Remittance summaries explain the cash handoff.
- Sorting Center explains Receive → Scan → Sort → Dispatch. Its inline forms wrap cleanly, action labels stay intact and tablet/phone controls retain a 44px touch height.
- Tracking uses a smaller, left-aligned search panel with a clear instruction. Notifications avoid duplicate outer padding.
- Supporting delivery pages use the smaller heading/panel scale. The existing compact Deliveries list and printed shipping-label styles remain scoped separately.
- Dark-mode account text and select indicators are readable.

The shared filter-label helper previously treated a label generated for one input as the following select's label. It now creates and associates a separate label for each field. Availability and Category also have explicit label names.

## Main files

| Files | Purpose |
| --- | --- |
| `public/css/logistics-workspace.css` | Common page density, tables, forms, stats, guides, tracking and responsive controls. |
| `public/css/logistics-dashboard.css` | Compact overview, summary tiles and charts. |
| `public/css/logistics-deliveries.css` | Scale for shipment details, scan and forms, excluding printed labels. |
| `public/css/logistics-shell.css` | Shared dark-mode account text and select indicator. |
| `public/js/logistics-workspace.js` | Correct filter labels and their field associations. |
| `resources/views/logistics/layouts/app.blade.php` | Consistent main padding and page-header spacing. |
| `resources/views/logistics/partials/dashboard-content.blade.php` | Clearer next-action and date-filter wording. |
| `resources/views/logistics/*/index.blade.php` | Page descriptions, sorting guide, tracking entry and notification spacing; 13 index templates changed. |

## Verification

- 176 browser page/viewport checks passed for admin and staff at 1366, 768, 390 and 320px: no horizontal page overflow or JavaScript errors; index, create and representative detail pages rendered successfully.
- 54 final checks passed: PHP blocks/permission conditionals, correct consecutive filter labels, sorting button fit and touch sizes, captured Receive submission with its selected center, sidebar staying expanded after navigation, settings search, password-dialog open/Escape, dark themes and staff denial on admin routes.
- All 36 before/after rendered form contracts match: destinations, methods, field names and select option values are unchanged.
- All 15 changed Blade templates compiled and passed PHP syntax checks. JavaScript syntax and git whitespace checks passed.
- Measured desktop examples: center row median decreased from 103px to 68px, rider rows from 101px to 79px and transaction rows from 87px to 69px. Page headings were approximately 38px before and are 28px now.

QA used a localhost-only preview with a read-only database transaction. Record-changing browser requests were intercepted; actual receive, account changes, password updates, messages and emails were not executed. No application routes, controllers, permission rules or database schema were changed. The existing uncommitted project work was preserved.

The changes use directly served, versioned public CSS/JS and Blade; no dependency installation or asset build is required. Refresh an open page with Ctrl+F5 to reload the styles.

Local screenshots, baseline backups, hash manifests and check results are in `C:/HeatGuard/logs/logistics-compact/`. The temporary preview is stopped after verification; the existing application on port 8000 is left running.
