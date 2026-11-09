# Store vouchers and direct login

## What changed in the website

- Open a seller's store and select **Vouchers** beside Products and Reviews. The count shows available offers for that store.
- Select **Claim voucher**. The button becomes **Claimed · ready at checkout**. At checkout, select the claimed offer under Vouchers; existing store, minimum-spend, expiry, usage and stacking rules still apply.
- Guests can browse store offers and select **Log in to claim**.
- The main **Vouchers** page now lists available offers even when the buyer's cart is empty. Previously, store vouchers appeared only after adding that store's products to the cart.
- Primary seller accounts now enter **Seller Dashboard** directly after login. Buyer-only accounts enter **Shop**. Accounts stored as buyers that also have an approved store retain the role picker. Existing manual role switching remains available.

`seller1@invoiz.test` was checked against the configured database using read-only queries. It has a primary seller role and an active, approved store. Its redirect now resolves to `/seller/dashboard` and selects the correct store. No account records were changed. No migration is required.

## Verification

The focused regression command passed **61 tests with 633 assertions**:

```powershell
php artisan test tests/Feature/StoreVoucherDiscoveryAndLoginTest.php tests/Feature/CheckoutVoucherScopeTest.php tests/Feature/SellerVoucherStatusTest.php tests/Feature/BuyerMobileIntegrationTest.php --compact
```

The new cases cover empty-cart discovery, store ownership and overlapping legacy IDs, inactive/expired/future/exhausted offers, guest access, repeat claims, pagination, seller and buyer routing, manual switching, email verification and blocked accounts. Tests use isolated SQLite fixtures.

Browser checks passed for store, claimed, guest and general voucher screens at 320, 390, 768, 1366 and 1920 pixels. They check card visibility, claim controls and page overflow. Mobile and desktop screenshots were visually reviewed. The design skill's inspector timed out connecting to Chromium; Playwright provided the successful browser checks and screenshots instead.

PHP syntax checks, Blade compilation and `git diff --check` passed.

## Existing test limitations

The broader `BuyerWebShoppingTest` still has eight failures involving base/variant cart expectations, an old product-button assertion, search fixtures and checkout submissions. The same eight cases fail when the affected controller, model and middleware classes are loaded from their pre-change HEAD versions. Those unrelated shopping behaviors were left unchanged. The focused checkout voucher, seller voucher and mobile integration suites pass.
