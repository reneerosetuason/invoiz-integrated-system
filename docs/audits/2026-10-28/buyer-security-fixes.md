# Buyer application fixes and security review — 28 October 2026

This patch repairs verified buyer-app problems and shared backend weaknesses in `C:\Invoiz`. It preserves the earlier mobile redesign and the existing account, catalog and order data. The updated debug APK has been installed on the connected TECNO KI7 over wireless ADB. The local buyer web preview was rebuilt at `/buyer-app/`.

## Functionality repaired

| Area | Result |
| --- | --- |
| Cart | Enforces active variations, stock and the combined 200-unit limit. Cart mutations use a cart-header transaction lock. Deleted products/disabled variations remain removable. Unavailable quantities cannot be selected for mobile checkout. Existing selections survive quantity changes. |
| Checkout | Locks the cart, item/product/variation rows and voucher claims before recalculating the purchase. Repeating checkout after the cart is consumed cannot create a second order. Required ledger failures roll back the order, payment and cart mutation. Unexpected server/database details are kept out of checkout error responses. |
| Store browsing | Reviews now read the Laravel paginator correctly, show failures with a retry button, and support more pages. Product pagination works inside stores. Store logos are returned. Product-to-seller relationships use the correct user-key namespace. Suspended/unapproved stores are hidden from the public catalog and rejected by checkout. |
| Address book | Account → My addresses now lists saved addresses and supports adding, editing, changing the default and deleting non-default addresses. Editing uses the existing address endpoint rather than creating duplicates. The server retains ownership and default-address checks. |
| Registration | Legacy buyer registration uses the email-code flow and cannot issue an immediate token. Alternate login routes reject unverified accounts. Mobile registration follows the adult/password policy, and supports manual address entry when the external address list is unavailable or a city is missing. Pending accounts can verify email on the website before approval, without receiving a signed-in session. |
| Profile and sessions | Password changes require the current password and revoke other API tokens. Password reset revokes API tokens. API tokens have a configurable seven-day default lifetime. Revoked/suspended mobile sessions clear local credentials and remove private screens from navigation. Account widgets react to authentication changes. Late responses from an old token cannot clear a new login. Android app backup is disabled. |
| Orders | The detail screen shows each parcel and its delivery-code action when eligible. Delivered items have a return request/status action. Returns identify an exact `order_item_id`, preserving variation identity, and use delivery history/time rather than mutable `order.updated_at` to determine eligibility. Unambiguous legacy product-only return calls still work. |
| Notifications and messaging | Chat notifications open their conversation. Arbitrary/self chat recipients are rejected. Notification-read aliases cannot create markers for nonexistent/foreign notifications. Field-validation errors are shown clearly instead of hidden behind a generic failure. |
| Seller/logistics dependencies | Voucher editing uses the correct seller URL, initializes modal fields safely, includes free shipping and casts date fields correctly. Vehicle types no longer fail due to invalid PHP property access. The center-application step-label JavaScript syntax error is fixed. Build helpers now use Flutter exit codes instead of treating informational stderr diagnostics as failed builds. |

## Security controls repaired

- **File disclosure:** the storage fallback no longer searches arbitrary public/private directories. It rejects traversal, absolute/alternate-stream paths, disallowed extensions, non-image content and paths that resolve outside its public roots. Product photos continue to load.
- **Identity documents:** new buyer/seller ID, tax and permit uploads use a private disk. Owner/admin previews require authentication and ownership, use confined file resolution and private non-cacheable responses. The old public copies were copied to `storage/app/private`, verified by SHA-256, then removed: **84 public copies / 69 unique files**. Database path references were preserved. A subsequent dry run found **zero** remaining copies in those public document directories. Logos/product photos remain public.
- **Access control:** existing tokens cannot bypass buyer verification/approval or use logistics-only accounts for shopping. Creating a pending store application cannot unlock a pending buyer account. New seller applications do not mark an email verified merely because a form was submitted.
- **Store isolation:** catalog, order-item, delivery, remittance and return ownership uses seller **user IDs**. Sale/voucher records use seller **row IDs**, with compatibility only for legacy user keys that cannot collide with another seller row, including deleted rows. Cash confirmations, returns and balances no longer authorize by merging both namespaces. The regression fixtures deliberately create colliding primary keys.
- **Accounting:** required checkout/settlement ledger errors propagate and roll back. Remittance confirmation and order settlement are transactional and retryable. A multi-parcel order is not marked paid in full while another parcel is still in transit.
- **Authentication abuse:** shared account/IP limits cover buyer, shared, admin and seller login aliases plus the main registration/recovery routes. Existing OTP attempt limits remain in place. Store/product/review pagination inputs are bounded.
- **Application privacy:** rider/center status details require a verified signed-in owner/admin, a verified email-bound browser session, or a short-lived capability issued only after a correct email code. Email alone no longer reveals application details. The website status form includes its verification controls.
- **Browser responses:** content-type sniffing protection, same-origin framing and a referrer policy are added without introducing a blanket CSP that would break the existing frontend scripts.

## Important compatibility and deployment notes

The application-status APIs are `/api/logistics/rider/application-status` and `/api/logistics/center/application-status`. API clients that previously sent only `email` must retain `application_access_token` from successful email verification and send it as the `X-Application-Access` request header. It is bound to that email and expires after 900 seconds. A verified account may use its authenticated owner access instead. The buyer mobile app does not use these logistics applicant endpoints. Other external clients must adopt the proof header before their next status lookup.

For another installation, deploy the new private-disk configuration and run `php artisan documents:privatize` first as a dry run, then `php artisan documents:privatize --apply`. The command checks collisions and verifies private copies before removing any public copies. Keep `storage/app/private` outside the web root. Do not publish it through a storage symlink. Do not copy old identity-document folders back into `public/storage`. Keep a private backup of the private disk with the database.

The installed debug APK uses `http://127.0.0.1:8000/api` through `adb reverse tcp:8000 tcp:8000` on the current wireless-debugging connection. The laptop API server and that connection must remain available. A distribution build needs the deployed HTTPS API address and release signing.

## Verification performed

- **103 isolated backend tests / 1,451 assertions passed.** The buyer/security/commerce/web/shared fixtures use SQLite memory rather than migrating the real application database.
- **39 Flutter tests passed**, including session expiry, late responses, paginated reviews, return line identity, delivery-code URLs, address updates and unavailable-cart selection.
- Flutter analysis has **no errors or warnings**; existing informational style/deprecation notices remain.
- **58 changed PHP files passed syntax checking.** `git diff --check` found no whitespace errors.
- **97 real catalog image URLs** returned HTTP 200 with image content. Missing catalog photos without a stored image still use the explicit placeholder; catalog content was not fabricated.
- Live guest checks confirmed category/catalog responses, invalid pagination validation, protected-cart denial, encoded traversal denial and protected-document denial.
- Browser checks for the shop, application-status form and application wizard returned HTTP 200 with **no page JavaScript errors**. The status form's verification gate was exercised without sending email. All browser mutations were blocked during these checks.
- Android APK and buyer web builds passed. The updated Android app launched successfully on the connected phone, showed the catalog and had **zero Flutter errors in its current process**.

No real test purchases, account registrations, cash confirmations, refunds or verification emails were submitted. The existing phone session/data was retained. Ordinary application reads may update normal session/token metadata. Identity documents were copied and hashed for protection, not opened for review or included in reports.

## Work still requiring staging or deployment follow-up

This is a verified repair pass, not proof that every possible defect or attack has been eliminated. The framework/dependency advisory work identified in `security-and-functionality-review.md` still needs a supported-version upgrade and regression validation. No major dependency upgrade, migration-history rewrite or live database migration was attempted here.

The in-memory tests do not certify MySQL locking under concurrent workers. Reconcile the existing migration history on a backup/staging database, validate a clean install/upgrade, then test the full buyer → seller → rider → remittance lifecycle with isolated accounts. Real SMTP delivery, Google OAuth, production TLS/proxy/cookie configuration, external bank handoffs and physical rider delivery were not exercised in this pass.

The old traversal route could potentially have exposed server files while publicly reachable. Review exposure history/access logs and rotate affected credentials if that deployment was exposed; removing the route cannot undo an earlier disclosure. Do not publish development/debug builds or development servers as the production service.

## Main code locations

- `routes/shared/storage.php`, `app/Support/AssetFiles.php`, `app/Console/Commands/PrivatizeMarketplaceDocuments.php`: confined public files and protected identity documents.
- `app/Http/Middleware/AuthenticationThrottle.php`, `EnsureBuyerApiAccess.php`, `EnsureAccountActive.php`: login limits and account permissions.
- `app/Http/Controllers/Buyer/Api/CartController.php`, `OrderController.php`: authoritative stock, checkout and return behavior.
- `app/Support/Payments.php`, `Remittances.php`, `Sales.php`, `Shipping.php`, `app/Models/Seller.php`: financial transactions and explicit seller identities.
- `app/Support/ApplicationStatusAccess.php`: email-bound application-status permissions.
- `buyer_mobile/lib/services/api_service.dart`, `auth_service.dart`, `widgets/auth_service_provider.dart`, `main.dart`: request errors and reactive session state.
- `buyer_mobile/lib/screens/addresses_screen.dart`, `add_address_screen.dart`, `order_detail_screen.dart`, `seller_store_screen.dart`: the added/fixed mobile actions.
- `tests/Feature/BuyerSecurityRegressionTest.php`, `buyer_mobile/test/buyer_security_test.dart`: regression coverage for the repaired controls and mobile flows.

The earlier assessment and mobile redesign report are retained unchanged for comparison. The working changes remain uncommitted for review.
