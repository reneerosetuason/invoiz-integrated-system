# INVOIZ Integrated System

Single Laravel backend for the marketplace (buyer, seller, admin) and the
logistics/rider operations center, with the separate Flutter rider client in
`rider_mobile`.

The Flutter buyer client is integrated under `buyer_mobile`. Its mobile
verification endpoints use the existing `users` OTP fields; mobile registrations
retain email verification and administrator approval, now available in the
shared Admin Buyers page. The existing website registration policy is unchanged.
See [buyer integration and feature coverage](docs/buyer-mobile-integration.md).

Shared-connection verification was added on 28 October 2026. Run
`php artisan invoiz:check-backend` to check all application models, API-token
storage, authentication providers, database-backed sessions/cache/queue, and
core role tables without changing data. Buyer/seller central-entry redirects
now stay on local routes; legacy external platform URL settings are unused.
See [shared backend verification](docs/shared-backend.md) for the current
deployment contract. Historical phase results below are not a current full-suite run.

This document is the authoritative account/role and authorization reference as
of the Phase 15 audit. The application code remains the source of truth. Phase
15 added active-account enforcement to protected buyer APIs, hardened the
logistics admin middleware, and rejected inactive or blocked legacy API logins.
No schema, migration, or database state was changed.

## Flutter rider client

The Flutter app is kept as a separate frontend under `rider_mobile`; it uses
the existing `/api/logistics` endpoints and does not connect directly to
MySQL. Setup, API configuration, supported flows, and Flutter verification
commands are documented in [`rider_mobile/README.md`](rider_mobile/README.md).

## Scope and sources

Audited sources:

- `app/Models/User.php`, `Seller.php`, `Rider.php`, `LogisticsCenter.php`,
  `LogisticsCenterApplication.php`
- `app/Http/Middleware/*` and `app/Http/Middleware/Logistics/*`
- `routes/web.php`, `routes/api.php`, `routes/logistics/web.php`,
  `routes/logistics/api.php`, `routes/logistics/auth.php`
- `app/Http/Controllers/Shared/UnifiedAuthController.php` and the legacy/login
  controllers
- `app/Services/RiderAccountProvisioner.php`,
  `app/Services/CenterApplicationService.php`
- `database/migrations/*`, `database/factories/*`
- Account/role regression tests under `tests/Feature/*`

Historical notes:

- `docs/DATABASE_CHANGES.md` describes the older rider schema/API snapshot. Some
  endpoint names and seed-account notes there are outdated; use this file and
  the current routes/migrations for account and authorization decisions.
- `README.md` contains current local setup instructions. The obsolete Docker/bootstrap
  instructions were removed during the unused-file cleanup.

## Local verification

Use the repository's MySQL test configuration:

```powershell
php artisan test -c phpunit.mysql.xml
```

Focused account/authorization checks:

```powershell
php artisan test -c phpunit.mysql.xml tests/Feature/BuyerApiSecurityTest.php
php artisan test -c phpunit.mysql.xml tests/Feature/VerifyOtpFlowTest.php
php artisan test -c phpunit.mysql.xml tests/Feature/AdminAuthorizationTest.php
php artisan test -c phpunit.mysql.xml tests/Feature/SellerLifecycleConsistencyTest.php
php artisan test -c phpunit.mysql.xml tests/Feature/LogisticsAccessAlignmentTest.php
php artisan test -c phpunit.mysql.xml tests/Feature/LogisticsStaffMiddlewareSecurityTest.php
php artisan test -c phpunit.mysql.xml tests/Feature/RiderSuspensionAuditTest.php
```

Phase 15 focused run across the buyer API, OTP, admin, seller, logistics, and
rider security files: 87 tests, 0 failures, 748 assertions. All tests are
reported as `DEPR` because PHP 8.5 deprecates the `PDO::MYSQL_ATTR_SSL_CA`
constant; this is a deprecation notice, not a failure.

Do not run tests against the shared development database. The test config uses
its own MySQL database plus array-backed cache/session and array mail.

## Authentication summary

- There is one `users` table and one login identity across all areas.
- Web registration (`GET|POST /register`) always creates a **buyer** account:
  `role=buyer`, `status=active`, `account_status=active`,
  `approval_status=approved`.
- Registration does **not** log the user in unless the email was verified
  through Google. A 6-digit email OTP is required.
- Web login (`GET|POST /login`) verifies the password, account activity,
  marketplace approval state, and email verification. Unverified accounts are
  sent to `/verify` with a fresh OTP instead of being authenticated.
- `POST /verify` validates the OTP value, expiry, linked account, and attempt
  limit. A successful code verifies the email and authenticates the user.
- OTP behavior: 10-minute expiry, at most 5 incorrect attempts per 10-minute
  window, and resend/new-code issuance clears the attempt counter. A 60-second
  resend cooldown also applies.
- Marketplace admins use `/admin/login`; logistics users use
  `/logistics/login`; riders use `/logistics/login` for web messaging or the
  logistics API for the rider app.
- A single global logout also deletes the account's rows from the shared
  `sessions` table.

## Account fields and status precedence

| Field | Purpose | Notes |
|---|---|---|
| `id` | Shared account identity | Used by buyer, seller, admin, logistics, rider profile links |
| `email`, `password` | Login credentials | Email is unique; trim/canonical handling is used by current web flows |
| `role` | One stored primary role | Enum: `admin`, `seller`, `buyer`, `rider`, `staff`, `logistics_admin`, `logistics_staff`; default `buyer` |
| `is_admin` | Marketplace administrator flag | Canonical gate for `/admin/*` and `/api/admin/*` |
| `account_status` | Authoritative account state | `active`, `suspended`, or `deactivated` |
| `status` | Legacy/shared account state | Synchronized from `account_status` on save; fallback when `account_status` is empty |
| `approval_status` | User-level marketplace approval | Used only when the account has no seller row for the pending/rejected login block |
| `email_verified_at` | Email verification timestamp | Required by the unified web login; not universally enforced by legacy/API logins |
| `center_id` | Assigned logistics center | Assignment alone does not grant logistics access |
| `otp`, `otp_expires_at` | Email verification code state | Cleared after successful verification or when Google proves the email |

### Account activity

`User::isActive()` is authoritative:

1. If `account_status` is non-empty, only `account_status === 'active'` passes.
2. Otherwise, legacy `status === 'active'` passes.

The model's `saving` hook keeps both fields synchronized:

- `account_status=active` -> `status=active`
- `account_status=suspended` -> `status=suspended`
- `account_status=deactivated` -> `status=inactive`
- The reverse mapping applies when only legacy `status` is changed.

Important: if rows are edited directly in SQL with mismatched values,
`account_status` still wins for access decisions.

## Account/role matrix

| Stored role | Normal origin | Marketplace access | Logistics/rider access | Default destination |
|---|---|---|---|---|
| `buyer` | Unified registration, buyer API registration, seller application before approval | Buyer area, shop, orders, chat; seller center only if also linked to an approved seller row | None by default; may gain center or rider access through linked records | `/shop` |
| `seller` | Marketplace admin approval of a seller application | Buyer shop plus seller center | None unless separately linked | `/choose`, then buyer or seller |
| `admin` | Seeded/provisioned marketplace administrator with `is_admin=true` | Marketplace admin area | Logistics center only when the logistics role also qualifies, or when `role=admin` is accepted by logistics middleware | `/admin/dashboard` |
| `rider` | Rider application approval for a new/non-marketplace account | Buyer shop is still reachable through the shared buyer routes | Rider web messaging and rider API while the linked rider profile is approved and active | Rider app/entry |
| `staff` | Legacy logistics staff created in the logistics staff manager | None | Logistics center, center-scoped operations | `/logistics/dashboard` |
| `logistics_admin` | Logistics manager/admin account | None | Logistics center plus logistics admin actions | `/logistics/dashboard` |
| `logistics_staff` | Center application approval for a new account | None | Logistics center, center-scoped operations | `/logistics/dashboard` |

A user has exactly one `role`, but capabilities can overlap:

- An approved seller can use both buyer and seller surfaces.
- A buyer or seller can be assigned to a logistics center and keep their
  marketplace role.
- A buyer, seller, admin, or rider account can be linked to a rider profile.
- A role is not a permission matrix by itself; linked seller/rider/center
  records determine additional access.

### Legacy capability helpers

- `isBuyer()`: `role` is null, empty, or `buyer`.
- `isSeller()`: `role === 'seller'`.
- `isAdmin()`: `is_admin` is truthy **or** `role === 'admin'`.
- `isStaff()`: `role` is `staff` or `logistics_staff`.
- `isLogisticsAdmin()`: `role` is `admin` or `logistics_admin`.
- `isLogisticsStaff()`: role is `admin`, `staff`, `logistics_admin`, or
  `logistics_staff`, **or** the account has a provisioned center assignment.
- `canSell()`: account is active and its linked seller record is effectively
  approved.
- `canUseBuyerArea()`: account is active, has no blocking marketplace approval
  state, and is a buyer, an approved seller, or a seller with an unapproved
  store.
- `hasBlockedMarketplaceApproval()`: true only when there is no seller row and
  user `approval_status` is `pending` or `rejected`.

### Marketplace admin identity caveat

The route middleware is intentionally stricter than the model helper:

- `User::isAdmin()` returns true for `is_admin=true` **or** `role=admin`.
- `EnsureMarketplaceAdmin` requires `is_admin` to be truthy.

Therefore an account with `role=admin` and `is_admin=false` can be treated as an
admin by role-redirect helpers but is denied by `/admin/*` and `/api/admin/*`.
This remains a known inconsistency and an open product decision.

## Provisioning flows

| Flow | Account result | Linked record result | Notes |
|---|---|---|---|
| Unified web registration | New `buyer`, active, approved, unverified | Optional cart header | OTP required; Google-verified signup is auto-verified and logged in |
| Buyer mobile API registration | New `buyer`, active, approved | Cart row | Existing mobile contract returns a Sanctum token immediately; no buyer OTP verification endpoint exists, so this flow remains intentionally separate from web OTP |
| Seller application, new email | New `buyer`, active, approved, verified at submission | `sellers` row pending, active | Applicant is logged in as buyer while waiting |
| Seller application, existing signed-in account | Role/status unchanged | Seller row pending | Buyer access remains available |
| Seller approval | User role becomes `seller` unless the account is admin, logistics staff, or an approved rider; user approval becomes approved | Seller approval approved, seller status active | Existing buyer access is preserved; login goes to `/choose` |
| Seller rejection | User role is preserved; user approval is not used to revoke buyer access | Seller approval rejected, seller status inactive | Rejected seller can reapply through the seller flow |
| Seller suspension | User account status becomes suspended | Seller status inactive | Seller and buyer access stop until reinstatement |
| Seller reinstatement | User account status becomes active | Seller approval approved, seller status active | Restores entitled seller/buyer access |
| Rider approval, new email | New `rider`, active, email verified, assigned center | Rider profile approved, verified, available | Credentials are emailed unless the account kept its own login |
| Rider approval, existing marketplace account | Buyer/seller/admin role and password are preserved; no center assignment is implied | Rider profile is linked to the existing user | Buyer/seller/admin can use rider surfaces through the rider profile |
| Rider approval, existing non-marketplace role | Converted to `rider`, password replaced, center assigned | Rider profile approved/verified | Covers accounts without their own marketplace login |
| Center application approval, new email | New `logistics_staff`, active, email verified, assigned center | New `logistics_centers` row; application approved/provisioned | Credentials are emailed |
| Center application approval, existing buyer/seller/admin | Role and password are preserved; center assignment is added | New center and approved application | Login is not reset; account gains center-scoped logistics access |
| Center application approval, other existing role | Converted to `logistics_staff`, password replaced, center assigned | New center and approved application | Non-marketplace accounts are provisioned as staff logins |
| Logistics staff created by manager | New legacy `staff`, active, email verified, assigned center | None | Uses the logistics staff resource |

### Effective seller approval

A seller is effectively approved when either:

- legacy `sellers.status === 'approved'`, or
- `sellers.approval_status === 'approved'` and `sellers.status !== 'inactive'`.

Suspension uses `sellers.status=inactive`. The `Seller::approved()` query scope
mirrors these rules.

### Effective rider eligibility

- A rider profile is approved when `riders.approved_at` is non-null.
- A rider profile is not inactive when `riders.status !== 'inactive'`.
- `hasApprovedRiderProfile()` requires both conditions.
- Rider API/web access also requires the linked `users` account to be active.
- Going online/accepting work additionally requires vehicle verification,
  an active vehicle type, and an unexpired license. These assignment checks do
  not replace account access checks.

### Provisioned center assignment

`hasProvisionedCenterAssignment()` requires `users.center_id` plus one of:

- role `staff` or `logistics_staff`, or
- role `buyer`/`seller` with a matching approved, provisioned
  `logistics_center_applications` row.

A `center_id` by itself is not enough. The center link is currently matched by
application email, so changing the account email can break the proof of
assignment. The center credential-resend action only finds roles `staff` and
`logistics_staff`; preserved buyer/seller/admin accounts therefore cannot use
that resend action.

## Authorization matrix

### Web surfaces

| Surface/route group | Middleware/enforcement | Requirement | Failure behavior |
|---|---|---|---|
| Public marketplace: `/`, `/shop`, `/products`, `/product/{id}`, `/store/{id}`, `/help`, `/sell`, `/login`, `/register`, `/verify`, password reset | None or controller-level guest logic | Public | Public page or normal validation error |
| Buyer pages: `/buyer/*` | `buyer` (`EnsureBuyer`) | Logged in, active, `canUseBuyerArea()`; approved dual-role account must select buyer | Logout and redirect to `/login`; dual account goes to `/choose` |
| Top-level buyer pages: `/cart`, `/checkout`, `/profile`, `/orders`, `/messages`, `/notifications`, `/wishlist`, `/vouchers`, `/help/complaint` | Inline session/`Auth` checks only | Logged-in buyer session or authenticated user | Redirect to `/login` from the individual controller/closure |
| Dual-role selection: `/choose`, `/switch-role` | `auth` | Authenticated; seller switch also requires an approved seller | Redirect to `/shop` or role destination |
| Seller center: `/seller/*` | `seller` (`EnsureSeller`) | Active account, effectively approved seller, `active_role=seller` | Redirect to `/seller/login`, `/shop`, or `/choose` |
| Marketplace admin: `/admin/*` | `auth`, `marketplace.admin` | `is_admin` truthy and account active | Guest -> `/admin/login`; non-admin -> 403; inactive -> logout + `/admin/login` |
| Logistics public applications: `/logistics/logistics-center/*` | Public plus throttled OTP endpoints | Public/valid application data | Normal validation/status page |
| Logistics dashboard and staff operations: `/logistics/dashboard`, deliveries, riders, pickups, tracking, reports, messages, profile | `auth`, `verified`, `staff` | `isLogisticsStaff()` and account active; email verified; controller-level center scoping | Logout + `/logistics/login` with error |
| Logistics admin actions: rider applications, center applications, staff, centers, service areas, pickup routing | `auth`, `verified`, `staff`, `admin` | Outer staff check plus role `admin` or `logistics_admin` | 403 or logistics login redirect |
| Rider web messaging: `/logistics/rider/*` | `auth`, `rider.web` | Approved non-inactive rider profile and active account | Redirect to `/logistics/login` |
| Logistics auth: `/logistics/login`, logout, verification, password screens | `guest`/`auth` plus `LoginRequest` | Valid credentials, logistics access, active account; marketplace `is_admin` accounts are rejected from this login | Validation errors; guest remains unauthenticated |

Center scoping is not implemented by `EnsureLogisticsStaff`. Middleware answers
"may this account enter logistics?" Controllers and queries answer "which
center may this account read or mutate?" Staff without a center are treated as
having no center data by the relevant controllers.

### API surfaces

| Surface/route group | Middleware/enforcement | Requirement | Failure behavior |
|---|---|---|---|
| Public marketplace API: catalog, stores, `/api/register`, `/api/login` | Public | Public credentials/data | Validation or 401 |
| Buyer API: `/api/cart`, addresses, orders, favorites, vouchers, notifications, chat, `/api/me`, `/api/user` | `auth:sanctum`, `account.active` | Valid Sanctum token and active account; buyer-specific routes add their own ownership or role checks | 401 for missing/invalid token; 403 for inactive accounts |
| Buyer delivery OTP: `/api/orders/{order}/deliveries/{delivery}/otp` | `auth:sanctum`, `role:buyer`, throttle | Token owner must be `role=buyer`, active, and authorized for the order/delivery | 403/404 |
| Marketplace admin API: `/api/admin/*` | `auth:sanctum`, `marketplace.admin` | `is_admin` truthy and active | 401/403 |
| Logistics API login: `/api/logistics/login` | Throttle 10/minute plus controller checks | Valid credentials, active account, approved non-inactive rider profile; issues rider-ability token | 422/403 |
| Logistics API base: `/api/logistics/logout`, `/api/logistics/user` | `auth:sanctum` | Valid token only | 401 |
| Rider API: `/api/logistics/rider/*` | `auth:sanctum`, `role:rider` | Token owner has an approved non-inactive rider profile and active account | 403 JSON |
| Rider private documents | `signed` | Valid short-lived signature | 403/404 |
| Logistics public API: vehicle types, address lookup, rider/center applications, status, email OTP, password reset | Public plus throttles | Public/valid application or verification state | Validation/429 |

The `role` middleware alias is not a generic role guard. It resolves to
`EnsureRiderRole`; `role:rider` means "approved rider profile", while any other
value means an exact `users.role` match plus an account-activity check.

## Status enforcement by surface

| Surface | Login check | Per-request check |
|---|---|---|
| Unified web login and `/verify` | Active, approval, email verification, logistics-account routing | Buyer/seller/admin/logistics middleware as applicable |
| Logistics web | Active, logistics access, marketplace-admin exclusion | `EnsureLogisticsStaff` and `EnsureAdmin` each check active on every request |
| Rider web | Approved rider profile required by the middleware | Active account and approved rider profile on every request |
| Rider API login | Active account and approved rider profile before token issue | `EnsureRiderRole` checks profile and active status on every rider route |
| Marketplace admin | `is_admin` and active status required at `/admin/login` | `EnsureMarketplaceAdmin` checks active status on every request |
| Seller web | Active account, approved seller, pending/rejected seller state | `EnsureSeller` checks active and effective approval on every request |
| Buyer web chat (`/buyer/*`) | Active and `canUseBuyerArea()` | `EnsureBuyer` repeats the check |
| Top-level buyer pages | No unified login controller; inline session/Auth checks | Inline checks only; no central active/approval guard |
| Buyer API | `Api\AuthController` checks activity and blocking approval | `auth:sanctum` plus `EnsureAccountActive` on the protected buyer group and `/api/user` |
| Legacy `/api/auth/*` | Password plus active and marketplace-approval checks; no email-verification check | Token-only routes afterward |

## Known inconsistencies and open decisions

Phase 15 closed the missing active-account guard on protected buyer APIs and
added a direct activity check to the logistics `admin` middleware. The
following items remain open:

1. **Marketplace admin identity is split.** `User::isAdmin()` accepts
   `role=admin`, but `/admin/*` and `/api/admin/*` require `is_admin=true`.
   Decide whether `role=admin` should remain display-only or become a real
   admin grant.
2. **Top-level buyer pages lack the `buyer` middleware.** `/cart`, `/checkout`,
   `/orders`, `/profile`, `/messages`, and related pages rely on inline
   session/Auth checks. They do not consistently enforce active status,
   marketplace approval, or buyer-role authorization on every request.
3. **Buyer mobile API bypasses OTP.** `POST /api/register` issues a token
   immediately and `POST /api/login` does not require `email_verified_at`.
   There is currently no buyer mobile OTP endpoint; changing this contract
   would require a separate mobile verification flow.
4. **Legacy API auth remains unverified.** `/api/auth/login` now rejects
   inactive and marketplace-blocked accounts, but it still does not require
   `email_verified_at`; `/api/auth/register` has no OTP gate. Treat it as a
   legacy integration surface, not the primary account flow.
5. **Legacy buyer/seller login pages do not require email verification.**
   `/buyer/login` and `/seller/login` check activity/role/approval but not
   `email_verified_at`. `/admin/login` also does not require email verification.
6. **Center assignment is email-coupled.** Provisioned center access is proven
   by matching the user's email to an approved, provisioned application row.
   Changing the account email can remove access. Center credential resend only
   supports `staff`/`logistics_staff`, not preserved marketplace accounts.
7. **Legacy seller `status` can affect effective approval.** A legacy
   `sellers.status=approved` is accepted even if `approval_status` differs.
   Admin actions keep both fields consistent, but direct SQL can create
   ambiguous rows.
8. **`docs/DATABASE_CHANGES.md` is historical.** It documents older rider API
   names and seed-account notes that no longer match the current routes.
9. **Public catalog routing is inconsistent.** Public `GET /api/categories` and
   `GET /api/products` are re-declared inside the authenticated group, and the
   later authenticated route wins. The numeric `/api/products/{id}` route
   remains public, while `/api/stores` has no public index and
   `/api/stores/{seller}` overlaps the authenticated store show route.
   Consolidate these routes before relying on public catalog behavior.
10. **Four pre-existing admin smoke failures remain.** Full-suite verification
   after Phase 15 showed 287 deprecated, 4 failed, 1 passed, 1866 assertions.
   The failures are payout mark-paid 404, withdrawal approval 404, dispute
   refund 404, and seller chat send 405. They are not addressed in Phase 15.

## Regression coverage

| Test | Coverage |
|---|---|
| `tests/Feature/BuyerApiSecurityTest.php` | Active buyer API access, suspension with an existing token, status precedence, public endpoints, ownership, legacy login checks, route guards |
| `tests/Feature/VerifyOtpFlowTest.php` | OTP bypass prevention, wrong/expired/reused/cross-account codes, lockout/resend, registration and resend |
| `tests/Feature/AdminAuthorizationTest.php` | Marketplace admin web/API authorization, `is_admin` requirement, suspended admins, representative mutations |
| `tests/Feature/SellerLifecycleConsistencyTest.php` | Pending/approved/rejected seller transitions, buyer preservation, status precedence, rider/logistics overlays |
| `tests/Feature/LogisticsAccessAlignmentTest.php` | Center provisioning, incidental center IDs, rider profile linking, inactive/unapproved rider denial |
| `tests/Feature/LogisticsStaffMiddlewareSecurityTest.php` | Center staff access, incidental center IDs, cross-center rider denial |
| `tests/Feature/RiderSuspensionAuditTest.php` | Rider suspension, delivery unassignment, audit rollback, token denial |
| `tests/Feature/CenterApplicationConsentTest.php` | Center application email verification and terms consent |
| `tests/Feature/AdminSmokeTest.php` | Admin page/render and lifecycle smoke coverage, including the four known failures |

## Phase 15 security changes

- Added `EnsureAccountActive` (`account.active`) and applied it with
  `auth:sanctum` to the protected buyer API group and `/api/user`. Existing
  Sanctum tokens now receive `403` after suspension.
- Added a direct active-account check to logistics `EnsureAdmin`, including
  logout/session invalidation for browser requests and JSON `403` responses.
- Legacy `/api/auth/login` now rejects inactive and marketplace-blocked
  accounts. It still does not require email verification.
- Marketplace `/admin/login` now rejects inactive administrators before a
  session is established; `EnsureMarketplaceAdmin` continues to recheck every
  request.
- Unified web OTP and rider_mobile authentication boundaries were reviewed.
  The mobile buyer and legacy API registration flows remain separate existing
  contracts; no new OTP endpoint or schema was introduced.

## Phase 14 non-goals (historical)

The following were Phase 14 boundaries. Phase 15 later added the buyer API
active guard and hardened the logistics admin middleware.

- No role redesign or role enum change.
- No new middleware or route guard was added during Phase 14.
- No application, route, controller, model, middleware, migration, seeder, or
  test behavior changed during Phase 14.
- No database records changed, no migrations run, and no Git history changed.
