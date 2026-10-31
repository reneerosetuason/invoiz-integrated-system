Marketplace administrators have read-only rider access. Rider operations belong to dedicated logistics administrators and authorized center staff. This applies to accounts with role `admin` or the marketplace `is_admin` flag; that flag no longer grants rider-management authority.

| Operation | Marketplace admin | Logistics admin | Authorized center staff |
| --- | --- | --- | --- |
| View existing rider directory/details | Yes | Yes | Own center |
| Activate/suspend, reset credentials, reverify | No | Yes | Own center |
| Approve/reject applications, resend application credentials, open application documents | No | Yes | No |
| Assign/reassign riders, bulk assign, auto-assign | No | Yes | Own center |

The general Manage Accounts action also rejects accounts with rider profiles, including buyer/seller identities and inactive riders. Legacy `role=rider` accounts are protected without requiring a linked profile. The same accounts show ?Managed by logistics? instead of status buttons.

The staff editor also rejects edits, activation, and deactivation of shared rider identities by marketplace admins; its details page remains read-only for those accounts.

Seller store suspension/reinstatement still changes the store. For a linked rider identity, it preserves the shared user's current account status, so it cannot disable rider access or undo a logistics suspension.

The existing logistics center restrictions, dedicated logistics roles, and provisioned center access for buyer/seller identities remain in force. Rider mobile self-service routes retain their existing authentication and ownership rules. No live database records, schema, environment values, mobile code, branches, or commits were changed by this permission task. Existing address changes in the working tree were preserved.

Implementation:

- `app/Models/User.php`: shared rider-management permission.
- `app/Http/Middleware/Logistics/EnsureRiderManager.php`, `bootstrap/app.php`, `routes/logistics/web.php`: reject forbidden requests at the route boundary.
- `app/Http/Controllers/Logistics/{RiderController,RiderApplicationAdminController,DeliveryController,StaffController}.php`, `app/Services/RiderAccountProvisioner.php`: enforce permissions at controller/service mutation boundaries.
- `app/Http/Controllers/Admin/{UserManagementController,SellerController}.php`: protect shared rider identities from account/store status controls.
- Admin, logistics rider/delivery views, and shared navigation: remove unsupported management controls and keep rider directories read-only for marketplace admins.
- `tests/Feature/MarketplaceRiderManagementTest.php`: forbidden mutations, unchanged records/passwords, private documents, shared identities, store restrictions, allowed logistics management, and cross-center denial.
- `tests/Feature/{DispatchDistance,RiderCompliance,RiderLicenseExpiry}Test.php`: positive rider-management fixtures now use dedicated logistics administrator identities without marketplace admin privileges.

Verification uses an isolated SQLite copy of the existing integration test schema. Production data was not seeded or migrated. The final relevant suite passed **118 tests / 970 assertions**. This includes ten new permission and shared-identity cases. PHP syntax, Blade compilation, formatting checks for the new PHP files, and changed-file whitespace checks pass.

The broader test run also identified existing limitations:

- Three `SellerLifecycleConsistencyTest` cases fail for new/rejected seller-applicant login and unapproved-account verification redirects. The same three failures were reproduced using the original HEAD `User` and `SellerController` classes loaded from temporary files, without overwriting the working tree. They are excluded from the final passing permission regression run and remain unresolved.
- Fifteen `AdminSmokeTest` cases cannot complete setup on SQLite: the existing `AdminSeeder` inserts `COALESCE(created_at, NOW())`, which fails on this fixture. The configured database user also lacks access to the existing separate MySQL test schema. No production seeder or database privilege was changed to bypass this limitation.

Detailed logs and isolated test configurations: `%TEMP%/invoiz-admin-rider-access-20261030/`.

## Global Logistics Admin identity

The global Logistics Admin is the fixed user ID `18` with email
`logistics_admin@test.com`, role `logistics_admin`, and `is_admin = false`.
`User::isLogisticsAdmin()` is the single authorization predicate; a matching
role on any other user is not global access. Platform Administrators remain
separate and retain only permissions explicitly granted to them.

Ordinary account management, staff provisioning, center/company approval,
password recovery, and model updates/deletes cannot alter the designated
account's identity, credentials, role, statuses, or company assignment. The
logistics seeder validates the designated identity without creating or
updating it. The pending company migration validates ID 18 without reading its
password hash or modifying its user row.

No separate singleton-assignment table is added: the network assignment is a
fixed user ID rather than an editable runtime assignment, and a table not used
by the authorization predicate would create a second source of truth. No
migrations or database changes were run.

## Logistics company migration safety and partial recovery

Migration `2026_10_30_190000_create_logistics_companies_and_scope_centers`
is restricted to the MySQL schema named `invoiz_ecommerce_test`. It aborts
before schema work if Laravel's active connection reports any other database.
It also requires user ID 18 with the approved email, `logistics_admin` role,
non-platform-admin flag, and active statuses, and exactly one stored
`logistics_admin` role. It never selects or updates the designated account's
credentials. The three approved centers are resolved by exact names and each
must have exactly one match.

The migration has two permitted starting schemas:

- **Clean:** none of its seven target tables or four target columns exists.
- **Known partial state from the failed 2026-10-30 run:** `logistics_companies`
  and `logistics_company_ownership_histories` exist with their exact expected
  columns; the company table has its expected primary/unique/status indexes
  and creator foreign key; the history table is empty and has no unexpected
  indexes or foreign keys. Its expected center/changed-by foreign keys and
  center/time index may be absent or already present under the explicit names
  `lcoh_center_fk`, `lcoh_changed_by_fk`, and
  `lcoh_center_created_at_idx`. The `users.logistics_company_id` and three
  `logistics_centers` ownership columns exist with the expected nullable
  definitions, indexes, and foreign keys. All those added ownership fields are
  still null. The five fleet/transfer tables do not exist. No company or
  ownership-history rows exist.

Any other mixture of present/missing tables, columns, indexes, or constraints,
unexpected table definitions, or ownership data aborts for manual
reconciliation. The migration preserves valid objects in the known partial
state, adds only missing history constraints/index, and creates only the five
missing fleet/transfer tables. Explicit short history constraint/index names
avoid MySQL identifier-length failures.

Company and staff assignments remain transactional and guarded. The migration
rejects ownership by another company, rejects conflicting staff company
assignments, updates only null ownership fields, and skips an already matching
assignment without resetting verification timestamps. It inserts an
`approved_assignment` history record only when no matching record exists and
aborts if duplicates already exist. Existing business data is not deleted or
rewritten; the only planned data changes are the approved company/center/staff
assignments and their audit records. A successful matching record is not
duplicated if assignment code is re-entered.

DDL transaction behavior varies by database engine, so another failure may
leave a different partial schema. Stop after any failure. Do not retry
blindly, run `down()`, or remove schema objects. Re-check the active database,
migration status, every target table/column/index/foreign key, company rows,
approved-center assignments, staff assignments, and ownership-history rows
read-only. Proceed only if the schema still matches one of the explicitly
accepted states; otherwise prepare a separately reviewed recovery plan. The
migration is forward-only: `down()` throws and does not drop business data.
Migration 200000 must remain pending until 190000 is fully reconciled and
verified.
