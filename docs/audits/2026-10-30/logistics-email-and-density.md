Rider decision emails and the authenticated logistics admin/staff interface were refined together, preserving the existing delivery, dispatch, center, account and permission behavior.

Email findings and fixes:

- Application 11 was approved using an existing marketplace identity. The old approval code intentionally skipped email for those identities. Approval now always sends a notice: new rider identities receive their temporary credentials, while existing identities receive instructions to use their current INVOIZ password.
- Existing identities can resend their approval notice without changing their role or password. Primary rider identities retain the credentials-resend action; a failed send restores the previous password, with a conditional update to avoid overwriting a concurrent password change.
- Rejection sends the recorded reason. A protected **Resend Rejection Email** action retries the saved decision without rejecting the application again or creating an account. Pending applications and marketplace administrators cannot use it.
- Mail failures retain the saved approval/rejection and show a warning with a retry instruction instead of a success banner.
- SMTP authentication succeeded from the command line and the phone backend on port 8001, but failed from the website server on port 8000. Three overlapping project PHP server trees were found on port 8000. They were replaced with one network-enabled development server at the same address. SMTP connection/authentication then passed from both running web backends. The phone backend was left running.
- The previously skipped approval notice for application 11 was sent once using the configured SMTP service and accepted by SMTP. Its existing password was not changed. The recovery was recorded in the application's audit log. Inbox placement was not verified, and no historical rejection emails were sent to unrelated applicants.

The UI uses a smaller operational scale across the shared logistics layout: sans-serif page titles, compact summary figures, reduced panel spacing, smaller desktop controls, and consistent bordered action buttons. Touch devices retain 44px action targets and 16px input text.

Records remain actual single-line table rows on phones, tablets and desktops. All columns remain available through horizontal scrolling confined to each table. Scrollable regions support keyboard focus and have a visible scrolling hint. Table headers and desktop Actions columns remain visible, action labels are readable, and destructive actions have a distinct treatment. Dark-mode inputs and table headers have explicit readable colors. Filters align in two columns on phones; empty-table messages remain readable without scrolling to the middle of a wide table.

Main files:

| File | Change |
| --- | --- |
| `app/Http/Controllers/Logistics/RiderApplicationAdminController.php` | Existing-account approval notices, safe retries, rejection resend, warning states |
| `app/Mail/RiderAccountApprovedMail.php` | Approval notice with optional temporary credentials and an appropriate subject |
| `resources/views/logistics/emails/riders/account-approved.blade.php` | Existing-login and new-login instructions |
| `resources/views/logistics/rider-applications/show.blade.php` | Appropriate approval/rejection retry controls |
| `routes/logistics/web.php` | Rejection-resend route inside existing logistics-admin/rider-manager restrictions |
| `resources/views/logistics/layouts/app.blade.php` | Warning banner and versioned shared presentation assets |
| `public/assets/logistics/css/logistics-density.css` | Compact operational typography, forms, tables, action buttons and dark-mode adjustments |
| `public/assets/logistics/js/logistics-density.js` | Table scroll regions, accessible headers, action-cell identification, empty states and redundant date-separator presentation |
| `tests/Feature/RiderApplicationApprovalTest.php` | Approval/rejection mail, retry, failure recovery and authorization coverage |

Verification:

- 15 focused tests passed, including rendered mail contents, existing-login preservation, rejection reasons, rejected-only retries, forbidden marketplace-admin access, SMTP failures, saved decisions and password restoration.
- 133 relevant backend regression tests passed / 1,085 assertions on the isolated SQLite test database with fake mail. The same three previously established seller-login/verification failures remain excluded, as documented in `admin-rider-permissions.md`; this does not claim that the entire repository suite passes.
- 200 read-only browser page/viewport checks passed across 50 admin/staff route combinations at 1366, 768, 360 and 320px. These covered dashboard, deliveries and scanning, sorting, riders, pickups, tracking, transactions, remittances, reports, settings, notifications, messages, profile, application review and representative center/staff/service-area forms and details. No horizontal page overflow or JavaScript errors were found; table rows/cells kept their table display and no-wrap text.
- Rendered form destinations, methods, field names/types and select values matched before and after the presentation script/style were applied.
- Targeted interactions verified bulk select/unselect, cancelling the rider confirmation dialog without submitting, horizontal table scrolling and keyboard access, plus readable dark-mode control colors for both roles. Browser write requests were blocked throughout; the preview database connection was in a read-only transaction.
- Final targeted visual checks also covered dashboard figures, date-filter alignment and empty-table messages after screenshot inspection.
- PHP/JavaScript syntax, Pint and whitespace checks passed. The changes use directly served versioned public assets; a Vite build is unnecessary.

Logs, private local screenshots, SMTP receipts, browser checks and the read-only preview are under `%TEMP%/invoiz-logistics-refinement-20261030/`. The preview is stopped after review; the normal backend stays running on port 8000. Existing dirty work was preserved. No environment values, database schema, mobile application files, commits or remote branches were changed by this task.

Refresh existing browser tabs with **Ctrl+F5**. Use **Resend Approval Email**, **Resend Login Credentials**, or **Resend Rejection Email** on the application review page as appropriate. Start only one development server per port when restarting the project.
