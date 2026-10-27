# Logistics Deliveries list redesign — 27 October 2026

Implemented in **C:/Invoiz**, served at **http://127.0.0.1:8000/logistics/deliveries**. This is a presentation-only change. No routes, controller queries, endpoints, dependencies, database records, commits or pushes were added. Existing unrelated working-tree changes were retained. The sidebar source is unchanged by this task.

## What changed

- Header: existing description and actions retained, compact Fraunces title, existing thin icons, deep teal primary action; phone primary action fills its row.
- Status filters: all 12 original links and labels remain in one native horizontally scrolling snap row.
- Search: existing GET form, bindings, labels, placeholder, date constraints, filters, sorting and clear action retained. All filters are always visible; four columns on desktop, two at mid sizes/phones, one below 360px. The conditional administrator Handling center filter is also retained.
- Toolbar and bulk form: compact controls, clear disabled submit styling and readable counter. Existing selection script is unchanged, including eligible rows, 25-item limit, indeterminate select-all and disabled-state logic.
- Records: desktop rows measure **53px** including their divider; fixed-width table has no horizontal scrolling. Tablet rows use two lines; phone rows have labelled 44px actions. Sender/recipient/destination and rider/center/area are merged into concise lines with full title and screen-reader descriptions. Semantic table/header/cell roles remain available when CSS switches display modes.
- Status badges: the existing shared component now presents logistics statuses with neutral/teal/brick colors, thin icons and labels. Unknown statuses are neutral. Non-logistics rendering retains its previous branch. Muted brick uses a darker accessible ink than the example swatch.
- Pagination: existing links and query preservation retained, with one combined summary.
- Motion/accessibility: subtle one-time panel/first-ten-row entrances, 40ms row stagger, press feedback, animated tracking-link underline, reduced-motion support, 2px keyboard rings and readable placeholder/disabled colors. Content and native filters remain visible without JavaScript; the existing archive and bulk interaction enhancements still require JavaScript.

## Files changed

| File | Purpose |
| --- | --- |
| resources/views/logistics/deliveries/index.blade.php | Compact columns, responsive record markup, accessible full data, icons, single pagination summary |
| resources/views/logistics/partials/delivery-filters.blade.php | Search-icon wrapper; existing form and field contracts retained |
| public/css/logistics-deliveries.css | List-scoped responsive layout, controls, density, interaction states and motion; remove duplicated shared shell rules |
| public/js/logistics-deliveries.js | Keep list filters open on phones; existing modal support retained |
| resources/views/components/status-badge.blade.php | Shared logistics-only icon/neutral-teal-brick presentation; existing status labels retained |
| public/css/logistics-shell.css | New shared shell tokens, body/topbar foundation and logistics status presentation |
| resources/views/logistics/layouts/app.blade.php | Load shared shell stylesheet and mark its body; sidebar/polling state retained |
| public/css/logistics-dashboard.css | Remove duplicated light/dark body/topbar foundation now in the shared shell |
| public/css/logistics-workspace.css | Same small shared-foundation extraction; preserve its pre-existing work |
| resources/views/logistics/layouts/partials/form-controls.blade.php | Expose the existing chevron as one reusable CSS token; retain shared form behavior |

Reused: existing layout, Fraunces/Inter fonts, established ivory/deep teal palette, icon component, status component, per-page component, Laravel pagination, delivery theme, filters, bulk script and archive dialog. Created once: shared shell/status styling and the chevron token. No duplicated SVG chevron was introduced.

## Everything hidden, removed, merged or demoted

1. Repeated **Not recorded / Not set** placeholders are hidden from visual rows. Full From/To/Destination and rider/Center/Area descriptions remain in titles and screen-reader text; a completely empty party cell has one dash, visually omitted only on phones as requested.
2. **Normal priority** is screen-reader-only; other priority values remain visible chips with full titles.
3. Repeated From/To/Destination/Center/Area prefixes are demoted to the full accessible descriptions; actual names and routing values remain in the combined visible lines.
4. Desktop/tablet View/Edit/Archive labels become accessible icon buttons; their visible labels return on phones. Every original link/archive target remains tied to its own row.
5. Created date moves out of the shipment cell into its own column; the tracking link remains unchanged.
6. The old table footer and duplicate Laravel result-count paragraph are replaced visually by one **Showing X–Y of N · Page P of Q** summary beside the original pagination controls. All count values remain available.
7. The **More filters & sorting** disclosure is hidden because its fields are always shown. No filter is removed.
8. Long record strings are visually ellipsized, with full titles; complete party/routing descriptions also remain available to screen readers.
9. Repeated page-specific light/dark shell CSS is removed in favor of the shared shell stylesheet. No control or unique record value is deleted.

Column label changes: **Shipment & actions → Shipment**; **Status & priority → Status**. Added separate **Created** and visually hidden **Actions** column headers. Sender & recipient, Rider & routing and Amount retain their wording; selection remains conditional.

## Taste skill and explicit tradeoffs

Read the project skill fully: **C:/Invoiz/.agents/skills/design-taste-frontend/SKILL.md**, plus its critique reference. The user made its anti-slop rules binding.

- Use sentence-case headings instead of the requested tracked uppercase eyebrows and tiny labels.
- Use flat ivory/white/teal surfaces, with no gradient washes or edge fades and no decorative panel outlines. Real fields, table separators and operational control boundaries remain.
- Retain the established INVOIZ palette and two typefaces instead of a randomly drawn marketing direction. The skill explicitly excludes dashboard/app screens; no marketing hero, photographs, video, new live gadget or invented content was added to this operational page.
- The required dense 13/14px interface scale is retained, rather than marketing-sized body prose.
- Existing sidebar styling is outside scope; its existing gradient is preserved and documented as an inspector exception.
- The native status strip intentionally scrolls sideways. No additional JavaScript was introduced to force the active chip into view.

Inspector exceptions: existing sidebar gradient, existing ivory palette, dense operational body size, and inapplicable marketing picture/video/hero/motion requirements. Final computed inspection has **no unaccepted FAILs**. Two warnings are retained deliberately: muted secondary copy at AA contrast (reported 5.7:1), and the requested sideways status strip. Full-page source lint still recognizes inherited sidebar labels, overlay utility classes and shared-form utility classes; these are existing/out-of-scope markup or overridden presentation, not a claim that the entire repository's static markup lint passes. Targeted CSS/JS lint passes without findings.

## Verification and limits

- Rendered the actual Blade views using synthetic models for **8 scenarios**: logistics admin, logistics staff, platform admin, empty, no rider options, 50 eligible records, active filters and unknown status. No accounts or delivery records were created.
- Compared before/after contracts for every scenario: forms/methods/fields/options/hidden inputs, IDs, status URLs/current state, header/export URLs, eligible checkboxes, row action URLs/archive binding, pagination URLs and the inline bulk script. **All comparisons passed.** Generated CSRF values were normalized in the comparison; CSRF fields/directives remain present.
- Chromium checked **320, 360, 375, 390, 414, 768, 1024, 1100, 1280 and 1920px**: no page/table overflow or off-screen controls; desktop rows 53px; all filters open; one h1. Also checked the 640 CSS-pixel reflow equivalent of a 1280px desktop at 200% zoom, rather than claiming a native browser-zoom test.
- **21 additional browser checks passed**: actual GET form serialization, rows-per-page auto-submit/filter preservation/page reset, conditional controls, empty/unknown states, 44px mobile targets, font sizing, keyboard ring, reduced motion, dark mode and the first-ten-row animation stagger. No JavaScript exceptions were observed.
- Bulk behavior: zero selection disabled, select-all caps at 25, unchecked rows disable at the cap, unchecking re-enables them, indeterminate/reset states correct.
- Archive behavior: row-specific target, modal roles, Escape closing and focus return verified using synthetic rows. Real archive/bulk POST operations and CSV downloads against live records were not executed.
- Final shared-chevron rendering and selected value were checked using computed styles and final screenshots; five responsive overflow cases were repeated after that final CSS adjustment.
- Targeted taste lint: **5 CSS/JS files, zero findings**. JavaScript syntax check, PHP syntax check and Blade rendering passed. Focused `git diff --check` passed. Applied file hashes match the tested staging files. Routes, DeliveryController, sidebar and per-page component hashes are unchanged.
- **Build blocked by existing environment:** `npm run build` cannot find Vite. No dependencies were installed. The changed public styles/scripts are linked directly and do not depend on Vite output.
- **Relevant PHPUnit baseline blocked:** LogisticsExportTest / LogisticsRenderSmokeTest (14 tests) cannot access the dedicated test database `invoiz_ecommerce_test`; there were 14 setup errors before implementation. No database permissions or live database configuration were changed to bypass this.
- Visual verification used the actual rendered views with synthetic data and real browser assets, not an authenticated live delivery list. Authenticated end-to-end database writes, a native phone browser and native 200% browser zoom remain unverified.

Artifacts and before/after screenshots: **C:/HeatGuard/logs/deliveries-redesign/**, including verification.json, extra-verification.json, design-review.md, final-1280.png, final-390.png, inspect-complete/ and original/published manifests. This ignored local folder contains the harness; it is not an application dependency.

## Assumptions

- Blank/whitespace-only party and routing fields count as unset.
- Preserve the original eligible selection statuses exactly: waiting_for_rider, assigned, delivery_failed. Ineligible rows retain an empty selection cell when that column exists; no rider options still means no bulk form/selection column.
- Keep the existing conditional Handling center filter even though the brief's filter count does not mention it.
- Preserve existing non-logistics status rendering and the sidebar work already in progress.
- Existing title/tooltips plus full screen-reader descriptions are the appropriate place for long data within a strict one-line desktop ledger.
