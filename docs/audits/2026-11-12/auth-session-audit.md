# Authentication and session audit

Scope: browser login, email verification, role routing, and legacy buyer sessions, following the logistics login isolation change. This is not a complete application or security audit.

Eight grouped defects were reproduced in ten isolated scenarios. One redirect-loop regression was corrected during this audit; the other seven findings remain open. Priority below reflects the observed impact, not a claim that an account or payment was compromised.

## Findings

### 1. High: legacy buyer and seller login skip email verification — open

Sources: `app/Http/Controllers/Buyer/BuyerChatController.php:27` and `app/Http/Controllers/Seller/AuthController.php:33`.

An active, approved but unverified account can authenticate through `/buyer/login` or `/seller/login` using its valid password. Those actions do not check `hasVerifiedEmail()`. From that session, `POST /cart/add/{product}` succeeds while `email_verified_at` remains null. Two reproductions cover buyer and seller separately.

Expected: enforce the same verification boundary as the main login before allowing protected buyer mutations. Seller dashboard middleware still requires verification; this audit proves cart mutation, not order creation or payment bypass.

### 2. Medium: signed verification links authenticate ineligible accounts — open

Source: `app/Http/Controllers/Shared/Auth/VerifyEmailController.php:31`.

The already-verified branch authenticates a guest before checking marketplace approval, and the verification action does not check whether the account is active. A valid, unexpired signed link for an already-verified suspended buyer creates an authenticated session. A valid link for an already-verified pending buyer does the same.

Expected: check active status and applicable approval rules before authentication. The global active-account middleware rejects the suspended account on its next request. The buyer-context guard now ends the pending account's session on `/shop`. Sustained access for those accounts was not demonstrated.

### 3. Medium: another account's verification link mixes identities — open

Sources: `app/Http/Controllers/Shared/Auth/VerifyEmailController.php:31` and its `redirectForRole()` helper; `app/Http/Controllers/Shared/UnifiedAuthController.php::redirectForRole`.

While buyer A is signed in, opening a valid verification link for already-verified seller B preserves A's authenticated identity but sets `active_role=seller`, writes B's `seller_id`, and redirects to the seller dashboard.

Expected: handle the identity mismatch explicitly, without mixing one account's role/store session with another authenticated account. Seller authorization rechecks the authenticated user; unauthorized access to B's data was not demonstrated.

### 4. Medium: a pending rider application blocks normal buyer login — open

Source: `app/Http/Controllers/Shared/UnifiedAuthController.php:361`.

An otherwise valid buyer with an unapproved rider profile is rejected by `/login` because any rider row triggers the logistics-only check. `/logistics/login` also rejects the unapproved rider. `/buyer/login` still accepts the same eligible buyer.

Expected: retain legitimate buyer access while a rider application is pending, consistent with marketplace eligibility. This is inconsistent portal behavior, not a total lockout across every endpoint.

### 5. Medium: rejected account switch retains the previous buyer session — open

Sources: `app/Http/Controllers/Buyer/BuyerChatController.php:44` and `app/Support/BuyerSession.php:11`.

Start signed in as buyer A with the legacy buyer snapshot, then submit valid logistics-staff credentials to `/buyer/login`. The controller rejects staff buyer access and logs out the web guard, but leaves A's snapshot. `BuyerSession::current()` still resolves A and `/shop` renders A's buyer context although the guard reports a guest.

Expected: rejected switches must leave a consistent identity/session outcome. The ordinary explicit buyer logout clears the session; this finding concerns the rejected switch path and does not demonstrate access beyond the prior session.

### 6. Medium: freshly resent OTP is rejected due to stale attempt count — open

Sources: `app/Http/Controllers/Shared/Auth/VerifyEmailController.php:92` and `app/Http/Controllers/Shared/UnifiedAuthController.php:218`.

After five failed attempts, `/email/verification-notification` issues a fresh OTP without clearing `otp_verify_attempts_{id}`. Submitting the correct new code is still rejected by `/verify` while the previous limit remains cached. The separate `/verify/resend` action clears that counter.

Expected: both resend entry points should apply a consistent, deliberate retry policy and accurately explain any remaining cooldown.

### 7. Low: one resend dispatches two verification emails — open

Source: `app/Http/Controllers/Shared/UnifiedAuthController.php:322` and `:328`.

One `/verify/resend` request dispatches both `VerifyEmailNotification` and `OtpCodeMail`, each covering verification. Fake notification and mail assertions confirm both dispatches for the same recipient.

Expected: one coherent verification message per resend. This confirms application dispatch behavior; external email delivery was not tested.

### 8. Medium: ineligible marketplace accounts enter redirect loops — fixed

Source: `app/Http/Middleware/EnsureBuyerWebContext.php:29`.

Before this audit's correction, an authenticated pending buyer requesting `/shop` was redirected to `/shop` repeatedly. A primary seller with no linked store entered `/shop` -> `/home` -> `/shop` after login. This regression came from the earlier buyer-context guard.

The guard now ends an ineligible session if its destination would fall back into `/shop` or `/home`, regenerates the CSRF token, and returns to login with a visible error. Valid role destinations remain intact. JSON buyer-access rejection remains a 403.

Permanent regression: `tests/Feature/LogisticsLoginSessionIsolationTest.php::test_ineligible_marketplace_accounts_leave_the_session_instead_of_redirecting_in_a_loop` covers both account states and confirms the login page can render afterward.

## Validation and evidence

All reproductions used temporary in-memory SQLite fixtures, array sessions/cache, and fake mail/notifications. No real account status, password, verification timestamp, order, payment, or remittance was changed. No verification emails were sent.

- Initial audit: 10 reproduction checks passed, 80 assertions. These checks intentionally asserted observed defective behavior; they were not assertions that the behavior was correct.
- After the loop correction: 10 audit checks passed, 82 assertions. The two loop scenarios now assert session termination; the other findings remain reproducible.
- Focused login, verification, buyer integration, product chat, and voucher regression suite: 58 tests passed, 495 assertions.
- Broader checkout, return/refund, escrow, remittance, and driver-earnings run: 211 passed, 9 failed, 1,619 assertions. All nine failures are in `HubTransportDriverTripEarningsTest`, whose fixtures attempt to insert into an absent `logistics_companies` table. That class does not provision its schema and does not exercise HTTP middleware; the failures occur before its earnings scenarios can run. Driver-trip earnings coverage therefore remains unverified by this run. See `.tools/auth-audit-20261112/workflow-regression.log`. The broader suite is not reported as passing.
- PHP syntax checks and `git diff --check` passed for the correction.

Local audit reproductions and logs are under ignored `.tools/auth-audit-20261112/`: `AuthenticationBoundaryAuditTest.php`, `reproduction.log`, `reproduction-after-loop-fix.log`, and `loop-fix-regression.log`. The permanent suite does not encode the remaining defects as desired behavior.

Only the buyer-context middleware, its regression test, and this report were changed in tracked files for this audit. No order, return/refund, earnings, remittance, or messaging implementation was edited.
