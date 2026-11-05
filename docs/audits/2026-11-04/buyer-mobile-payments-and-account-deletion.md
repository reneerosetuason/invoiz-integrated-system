# Buyer mobile GCash and account deletion

Implemented the mobile continuation of manual GCash receipts and completed the
existing partial buyer deletion feature across Laravel Blade, Flutter, and the
shared backend. Existing uncommitted GCash website work was preserved.

## Buyer mobile payments

- Checkout offers COD and GCash. GCash requires a JPG, PNG or WebP receipt up
  to 5 MB, with gallery selection, preview, replacement, and upload feedback.
- Multipart checkout preserves selected cart item IDs and voucher claims.
  For multiple stores, the UI explains that the receipt accompanies every
  resulting order. COD clears the selected receipt.
- Payment status remains pending after upload. The existing seller order page
  provides the protected receipt link. Mobile buyers can open their own
  receipt in order details through an authenticated image request.
- Storage paths remain private. JSON exposes only `has_payment_proof`.

## Account deletion behavior

The existing `account_deletion_requests` table/model and User authentication
are reused. The account remains a User row with its original primary key;
archiving sets `users.archived_at`, `account_status=deactivated`, and legacy
`status=inactive`. Orders, payments, receipts, and transaction relationships
remain intact. No User soft-delete scope was added that would hide historical
relationships, and no permanent deletion is performed.

Both buyer interfaces provide the eight requested reasons, comments, a required
explanation for Other, password verification, the grace-period acknowledgment,
and a final confirmation after successful password verification. Passwords are
checked again during submission. Verification/submission/cancellation are rate
limited. The existing Google signup flow also requires a password during
registration; existing password recovery supports buyers who need to recover
that password. No second authentication system was introduced.

The request records submission time and an unchanged deadline 30 days later.
During grace, buyers keep account and order access, receive a visible warning,
and can cancel without administrator approval. Duplicate active requests are
rejected. Cancellation requires confirmation and is available strictly before
the deadline. Cancellation history remains stored.

Due requests with unresolved orders, deliveries, returns/refunds, complaints,
payments, payment transactions, escrow/remittances, or delivery settlement
transactions move to `on_hold`. The processor rechecks them hourly and archives
only once the blockers are resolved. It does not cancel transactions. Buyer
mutation requests lock the same User row used by the processor, serializing
account changes and archive eligibility checks. Existing account restrictions
or a change to another account role also prevent automatic finalization.

This is a buyer-only workflow. Integrated store/rider/hub-driver accounts are
excluded to avoid closing another role through buyer settings.

Finalization rotates the remember token, removes active Sanctum tokens,
database sessions, and outstanding password reset tokens, and completes the
request. Account-active checks enforce the archive marker even if another
status field is subsequently changed. Login, Google login, password recovery,
and existing sessions cannot restore archived account access.

The admin sidebar now includes **Account Deletion Requests**, with status
filters, buyer/email/request-ID search, request details, blocker information,
and audit history. Restoration requires an active platform administrator, a
fresh password check, confirmation, and an identity-verification attestation.
The administrator must complete the support identity check before attesting.
Only an account archived by this completed request can be restored; unrelated
restrictions are preserved. Normal account activation cannot bypass this
workflow. The historical request stays `completed` and records `restored_at`
and `restored_by`.

Submission, cancellation, reminders, holds, completion, and restoration create
audit events and send email through the existing mail infrastructure after
commit. System actions and administrator actions are distinguished; hold
reasons remain in audit history. Mobile notifications also derive account
updates from these events. Repeated processing does not duplicate notices.

The configurable notice in `config/account_deletion.php` explains transaction
retention and directs personal-data erasure requests to a separate support
privacy review. Archiving is not represented as permanent personal-data erasure.

## Database changes applied locally

Database: `127.0.0.1:3306/invoizdb`. Applied these explicit paths:

```text
2026_11_03_000001_create_account_deletion_requests_table
2026_11_04_130000_complete_buyer_account_deletion_workflow
```

The first existing migration created the previously absent request table. The
new extension adds hold/restriction history, reminder/restoration timestamps,
the restoring administrator FK, eligibility indexes, and nullable indexed
`users.archived_at`. Legacy request statuses `blocked`/`archived` normalize to
`on_hold`/`completed`. No conflicting User status enum is required.

Before/after snapshots contain 98/99 tables. All pre-existing business table
counts are unchanged; migration history gained two records. The new request
table is empty. No real buyer was requested for deletion, archived, or restored
during implementation. `.env` was unchanged.

Full pre-migration backup, retained privately and ignored by Git:

```text
storage/app/private/logistics-before-repair-20261104-121141.sql
SHA-256: ec778ee0da7141e2de9aef9d80d6f9aed8420e0580b28f45074c47fc393dfd3e
```

## Routes

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/profile/account-deletion` | Buyer explanation, form and current status |
| POST | `/profile/account-deletion/verify` | Fresh password verification |
| POST | `/profile/delete-account` | Confirm deletion request |
| POST | `/profile/cancel-deletion` | Confirm cancellation during grace |
| GET | `/api/account-deletion` | Mobile status, deadline, reasons and retention notice |
| POST | `/api/account-deletion/verify` | Mobile password verification |
| POST | `/api/account-deletion` | Mobile request submission |
| POST | `/api/account-deletion/cancel` | Mobile cancellation |
| GET | `/admin/account-deletions` | Administrator search/filter list |
| GET | `/admin/account-deletions/{deletion}` | Administrator request details |
| POST | `/admin/account-deletions/{deletion}/restore` | Verified administrator restoration |
| GET | `/api/admin/account-deletions` | Protected administrator API list |
| GET | `/api/admin/account-deletions/{deletion}` | Protected administrator API details |
| POST | `/api/admin/account-deletions/{deletion}/restore` | Protected administrator API restoration |

Submission uses `reason`, `additional_feedback`, `current_password`, and
`confirm_deletion`. Cancellation uses `confirm_cancellation`. Restoration uses
`current_password`, `identity_verified`, and `confirm_restore`. Status responses
include server-calculated `remaining_days` and `can_cancel`; timestamps include
timezone offsets. Buyer identity always comes from authentication.

GCash continues to use multipart `POST /api/orders/checkout` with
`payment_method=gcash`, `gcash_proof`, and normal checkout selection fields.
`GET /api/orders/{order}/payment-proof` streams only that buyer's receipt.

## Scheduler and deployment

`routes/console.php` schedules `buyers:process-account-deletions` hourly with
`withoutOverlapping()`. Laravel discovers the command in `app/Console/Commands`.
The command batches requests, isolates failures, and returns a failing exit
code if any request could not be processed. SQL transactions and state checks
make reminder, hold, archive, and restoration processing idempotent.

**The deployed server must invoke Laravel's scheduler every minute.** An hourly
schedule definition alone does not start a background process. For Linux:

```cron
* * * * * cd /path/to/invoiz && php artisan schedule:run >> /dev/null 2>&1
```

For Windows Task Scheduler, run `C:\php\php.exe` with arguments
`C:\Invoiz\artisan schedule:run`, start in `C:\Invoiz`, and repeat every minute.
Use the deployed application's actual paths and service account. Review the
application log and scheduler exit status. `php artisan schedule:list` verifies
the hourly entry; tests exercise the processing command. Existing mail
configuration delivers the notices; no new queue worker or SMS service is
required.

Other deployments must apply the receipt migration from the preceding task,
the existing deletion-table migration, and the extension using explicit paths
appropriate to that database. This repository has older imported migrations
marked pending; do not indiscriminately replay them or use `migrate:fresh`.
Rebuild/install the buyer Flutter app against the intended shared API URL.
Real-device gallery behavior and an Android APK build were not exercised here.

## Verification

- Focused Laravel coverage: **43 tests passed, 289 assertions** across account
  deletion, GCash proof, and checkout vouchers. SQLite fixtures/fake storage
  and notifications isolate tests from the real database. Coverage includes
  the exact deadline, grace access, reasons/confirmation/password validation,
  rate limits, duplicate prevention, cancellation history, order/delivery/
  refund/dispute/payment/escrow/settlement holds, rechecks, preserved records,
  revoked sessions/tokens, disabled logins, admin authorization/reauthentication,
  restoration restrictions, notices, and scheduler idempotency.
- Focused Flutter coverage: **14 tests passed**, including all eight new
  receipt/deletion tests and six existing checkout voucher tests.
- Full Flutter run: **205 passed, 5 failed**. All five failures reproduce on an
  isolated untouched `HEAD` export with the same SDK: four existing registration
  layout/Material assertions and one existing return-dialog submission test.
  Baseline and full logs are in the ignored `.tools` folder.
- A broader Laravel run also exposed unrelated fixture/expectation issues:
  `BuyerApiSecurityTest` requires an initialized database; registration fixtures
  omit rider lookup tables; an existing return test omits required evidence;
  and the buyer-admin page test expects the old text “Pending approval”. These
  checks are not represented as passing. No protected MySQL database was used
  for PHPUnit.
- Chromium checked the actual Blade buyer form at 390 px and administrator
  restoration page at 1280 px using isolated fixtures. Other-reason validation,
  failed password feedback, verification before confirmation, a single final
  submission, pending status, and responsive layout passed without page errors.
- PHP syntax, Blade compilation and diff whitespace checks passed. Flutter
  analysis found no errors or warnings; informational lints remain.
  Used the project's generated package configuration and matching local Flutter
  3.44.5 SDK. The system Flutter 3.35.5 SDK does not match that configuration.

## Main files

New backend files:

```text
app/Services/BuyerAccountDeletionService.php
app/Http/Controllers/Buyer/AccountDeletionController.php
app/Http/Controllers/Admin/AccountDeletionController.php
app/Console/Commands/ProcessBuyerAccountDeletions.php
app/Notifications/AccountDeletionNotice.php
config/account_deletion.php
database/migrations/2026_11_04_130000_complete_buyer_account_deletion_workflow.php
```

Updated backend integration: `app/Models/{User,AccountDeletionRequest,Payment}.php`,
buyer `ProfileController`, buyer API `AuthController` and `NotificationController`,
shared `AuthController` and `UnifiedAuthController`, admin `UserManagementController`,
`EnsureAccountActive`, `EnsureActiveWebAccount`, `AppServiceProvider`, and
`routes/{api,console,admin/web,buyer/web,buyer/api}.php`.

New views: `resources/views/buyer/account-deletion.blade.php` and
`resources/views/admin/account-deletions/{index,show}.blade.php`. Updated buyer
profile/layout and shared admin navigation link to the new interfaces.

New Flutter files: `buyer_mobile/lib/screens/account_deletion_screen.dart` and
`buyer_mobile/lib/widgets/account_deletion_banner.dart`. Updated mobile checkout,
account settings, order details, payment model, API image/multipart handling,
shared layout and update notifications.

Tests: `tests/Feature/BuyerAccountDeletionWorkflowTest.php`, the updated existing
`BuyerAccountDeletionTest`, `tests/Support/BuyerAccountDeletionDatabase.php`,
the shared buyer database fixture's archive field, and
`buyer_mobile/test/{gcash_checkout,account_deletion}_test.dart`.
