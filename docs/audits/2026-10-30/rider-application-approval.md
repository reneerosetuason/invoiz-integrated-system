The logistics administrator's approval failure was reproduced: selecting an active service area from a different logistics center caused `RiderAccountProvisioner` to throw a model-not-found exception (HTTP 404). Application 11 and its routes already existed; no database table or migration was missing.

The review form now filters service areas by the selected active center, clears incompatible selections, preserves valid old input after validation, and disables approval until both choices are valid. Centers without active coverage show setup instructions and a link that preselects the center on the service-area creation form. The script is pushed inside the layout component so it is included in the rendered page.

The controller validates active centers and active service areas belonging to the selected center. The provisioner rechecks the same conditions under transaction locks, returning field validation errors if coverage changes after the page was opened. Invalid assignments cannot create partial accounts. Existing marketplace identities keep their roles and passwords. Marketplace administrator restrictions remain intact; dedicated logistics administrators retain approval authority.

Files changed for this fix:

- `app/Http/Controllers/Logistics/RiderApplicationAdminController.php`
- `app/Services/RiderAccountProvisioner.php`
- `resources/views/logistics/rider-applications/show.blade.php`
- `resources/views/logistics/service-areas/create.blade.php`
- `public/assets/logistics/js/rider-applications/review.js`
- `tests/Feature/RiderApplicationApprovalTest.php`

Following the user's authorization to configure missing coverage, one live record was created:

| Field | Value |
| --- | --- |
| Logistics center | ID 8, `rerere`, Lumban, Laguna |
| Service area | ID 5, `Lumban`, active |
| Description | Service coverage for Lumban, Laguna. |
| Basis | Exact municipality/province match between the existing center and application 11 |

The creation and rationale are recorded in the application's audit log with a null maintenance actor, rather than impersonating a logistics administrator. No other center was configured. Application 11 remains pending and unassigned; no real rider login was provisioned and no real approval email was sent during verification.

Verification:

- Eight focused approval tests passed, 67 assertions: mismatched/missing/inactive assignments, direct service validation, no partial accounts, successful approval and generated-password API login, preserved existing logins, rendered form choices/scripts, setup preselection, and legitimate missing-application 404s.
- The relevant rider, logistics, authorization, dispatch, document, and seller regression selection passed 126 tests / 1,037 assertions on an isolated SQLite test database, with mail faked.
- Three previously reproduced, unrelated seller-login/verification failures remain excluded from that regression selection, as documented in `admin-rider-permissions.md`. This is not a claim that the full repository test suite passes.
- Headless Chromium checks passed at widths 360 and 1280: old selections restored, area filtering, center-change resets, missing-area instructions/setup link, valid approval enablement, no horizontal overflow, and no JavaScript errors. All non-GET requests were blocked during these browser checks.
- Screenshots of the approval form were inspected at mobile and desktop sizes. PHP and JavaScript syntax checks, Pint, and `git diff --check` passed.

Detailed logs, browser captures, the isolated database/configuration, and the live coverage-change receipt are in `%TEMP%/invoiz-rider-approval-20261030/`. Existing dirty work was preserved. This task required no schema, environment, mobile application, branch, commit, or remote changes.

To approve application 11, reload its review page, select center `rerere`, select service area `Lumban`, then use **Approve & Create Account**. Actual outbound email delivery depends on the configured mail service; the tests verify the application's mail handoff without sending real messages.
