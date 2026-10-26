Logistics dashboard redesign — 26 October 2026

The user confirmed the dashboard after login as the scope. The page at `/logistics/dashboard` now follows the established buyer/seller design: warm ivory, deep teal, Fraunces headings, Inter reading text, flat surfaces and restrained status colors.

**What changed**

- Today's activity and action queues lead the page. All-time and date-filtered shipment totals have separate labels so their periods are clear.
- Larger, sentence-case labels replace small uppercase captions. Data uses tabular figures and stronger secondary text contrast.
- Trends get more space, status counts remain visible beside their chart, and center performance uses horizontal bars with wrapped names.
- Each chart expands into a keyboard-accessible dialog with a table of exact values. Dialogs trap focus, close with Escape, restore focus to the opener and prevent background scrolling.
- The latest six deliveries and the searchable fifty-record browser share one section. Search, status/center filters, sorting, pagination and the full delivery-manager link remain available. Missing recipients display “Not provided.”
- Mobile records use labeled lists instead of squeezed desktop tables. The dashboard's sidebar becomes a drawer on small phones, opened from the topbar.
- Existing rider availability, workload, service areas, activity, financial totals and operational alerts remain present. Existing backend calculations, role/center scoping and route destinations are retained.
- The dashboard supports the existing dark appearance and reduced-motion preference. Theme changes update chart-label colors. Chart series use different line patterns as well as color.

**Application files**

| File | Change |
| --- | --- |
| `resources/views/logistics/dashboard.blade.php` | Page composition, brand asset loading, chart presentation, chart dialogs, readable record fallbacks and chart lifecycle handling. Existing `dashboardCharts()` and `detailedRecords()` functions remain. |
| `resources/views/logistics/partials/dashboard-content.blade.php` | Dashboard content and responsive records, with existing data, controls and links. |
| `public/css/logistics-dashboard.css` | Scoped theme, typography, responsive layout, dark theme, focus and reduced-motion styles. |
| `resources/views/logistics/layouts/app.blade.php` | Styles stack, dashboard-only mobile menu button, and defensive notification-feed error handling. |

**Related notification repair**

Staff browser checks uncovered an actual notification-feed HTTP 500: `notifications.center_id` and `notifications.audience` were missing. The existing migration was applied explicitly:

```powershell
php artisan migrate --path=database/migrations/2026_10_20_000001_add_center_scope_to_notifications_table.php --force
```

No new migration was needed. Its original policy is preserved: existing notices default to `global`; newly created notices can target a center or administrators. Both admin and staff notification JSON feeds then returned HTTP 200. Failed requests now produce a clear notification error message rather than assigning undefined data and throwing an Alpine error.

**Verification**

- Browser checks cover admin and staff at 320, 375, 390, 768, 1024 and 1440px, including the staff empty state. Fonts loaded, expected charts rendered, and no document overflow or JavaScript exceptions were observed in the passing run.
- Interaction checks cover the mobile drawer, all three chart dialogs and their tables, Tab focus trapping and Escape/opener focus, repeat chart opening, chart clicks/drill-down links, record search/pagination/reset, dark theme, empty date ranges, thirty-day trends, reduced motion and a deliberately simulated notification-fetch failure.
- `php artisan test --filter='CommerceRegressionTest|ExampleTest'`: 22 passed, 105 assertions. These are the existing focused tests from the functional repair; no new low-impact markup tests were added.
- Blade compilation, changed-template PHP syntax checks and `git diff --check` passed.
- The complete MySQL feature suite remains subject to the previously recorded test-database permission limitation. This redesign's browser harness uses read-only application requests; the explicitly named notification migration is the database change made during this task.

Final browser run: 12 role/viewport contexts and 10 interaction groups passed. Final inspector: zero failures with the documented dashboard exceptions, plus four accepted warnings covering existing chrome, brand ivory and UI type sizing.

Browser evidence and the repeatable local check are in `.taste-skill/logistics-dashboard/`. Final design-inspector screenshots and metrics are in `.taste-skill/logistics-dashboard-final/`.

**Taste-skill application and review**

The requested skill explicitly excludes dashboards/app screens. Its existing-brand typography, color, composition, contrast, control feedback and screenshot-review principles were applied to this operational UI. The reference was the existing seller dashboard; no new marketing direction was drawn, so there is no new direction seed or redraw. No photographs, footage, fabricated statistics or sample records were introduced.

The marketing inspector was still run before and after the redesign. Its final run documents exceptions for photographic/video quotas, theatrical entrance motion, oversized marketing focal type, metric labels mistaken for eyebrows, dense data/captions mistaken for running prose, and the sticky application header crossing text during arbitrary scroll sampling. These exceptions do not disable overflow, contrast, font loading or broken-image checks. Remaining warnings are assessed in the design notes; measurement results are not a substitute for screenshot review.

The working tree retains the earlier functional repairs. No commit was created.
