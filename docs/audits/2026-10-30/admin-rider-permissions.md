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
