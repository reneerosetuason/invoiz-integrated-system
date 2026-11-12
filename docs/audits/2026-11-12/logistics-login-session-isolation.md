# Logistics login and buyer session isolation

## Diagnosis

`logistics_staff@test.com` is active, has the `logistics_staff` role, and is not email-verified. Verification is therefore expected. Its role and verification status were inspected read-only and were not manually changed.

The incorrect buyer page came from two routing/session defects:

- Authenticated visits to `/home` and the shared login fallback did not handle logistics staff consistently, defaulting to `/shop`.
- `BuyerSession::current()` treated any authenticated account as a buyer and trusted a legacy buyer snapshot over the authenticated identity. Several older buyer routes also used the shared login directly without a buyer-role boundary.

The verification screen additionally lacked a default email when entered through the `verified` middleware, and its Back link pointed to the shared marketplace login. Logistics password login did not issue the code that screen asked for.

## Changes

- Logistics login clears stale buyer/store/role and intended-destination state, then uses the server-side logistics destination. An old `/shop` or checkout destination cannot override it.
- Unverified logistics password login issues or reuses a time-limited OTP and sends the existing verification notification. Verification remains required. Wrong or expired codes cannot unlock the dashboard.
- Shared home/login/verification completion routes send staff back to Logistics and approved riders back to the rider entry page. Unsupported logistics access cannot create a login redirect loop.
- The verification screen defaults to the authenticated account's email. For logistics accounts, Back posts to Logistics logout and returns to the Logistics login page, clearing the pending session rather than masquerading as a buyer.
- Buyer identity is limited to eligible marketplace accounts, including legitimate approved stores and valid legacy buyer sessions. The authenticated identity overrides a mismatching snapshot.
- A buyer-web middleware boundary redirects dedicated logistics users to their portal and denies legacy buyer mutations. Guests can browse as before; platform administrators can still inspect the public catalog. Legitimate buyer/seller shopping and role choice remain available.
- Marketplace approval status is not used to block an otherwise authorized staff account's email verification. Pending marketplace buyers still require approval before buyer access.

No passwords, account roles, center assignments, or verification timestamps were manually changed in the application database. No order, return/refund, earnings, assignment, or remittance service was edited.

## Files

- `app/Support/BuyerSession.php`
- `app/Http/Middleware/EnsureBuyerWebContext.php`
- `app/Http/Controllers/Logistics/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/Shared/UnifiedAuthController.php`
- `app/Http/Controllers/Shared/Auth/VerifyEmailController.php`
- `routes/buyer/web.php` (middleware group; existing route definitions retained)
- `routes/shared/auth.php` (`/home` role routing)
- `resources/views/shared/auth/verify.blade.php` (logistics cancellation control)
- `tests/Feature/LogisticsLoginSessionIsolationTest.php`

## Verification

- **57 tests passed, 483 assertions**: login/verification/session isolation, buyer mobile integration, buyer product chat, store vouchers, primary seller login, and dual buyer/seller choice.
- **238 tests passed, 1,812 assertions**: order, return/refund, escrow, payouts, and admin/hub/inter-hub remittance regressions.
- Tests cover stale intended URLs, cross-account snapshots, staff entering buyer pages or mutations, Back-navigation request paths, verification cancellation, valid/wrong/expired OTPs, signed verification links, approved riders, and pending buyers.
- PHP syntax, Blade compilation, route loading, and diff whitespace checks passed. Route cache was cleared.

Tests use isolated in-memory SQLite and fake notifications/mail. No verification email was sent to a real account during the checks. Existing unrelated messaging/mobile changes were retained; no commit, push, migration, or app publication was performed for this fix.
