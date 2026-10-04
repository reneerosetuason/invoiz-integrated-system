# INVOIZ Integrated — single backend (buyer + seller + admin)

New folder: `C:\Users\LENOVO\OneDrive\Desktop\Invoiz e-commerce\invoiz-integrated`
Original folders were NOT changed.

## What was merged
- Base: `invoiz admin acc/backend` (canonical `invoizdb` schema, admin + seller + buyer-chat, 30 migrations)
- Merged buyer storefront from `Invoiz-buyer-acc-web-master/backend`:
  `Web/HomeController`, `Web/AuthController` (verify/Google legacy), `Api/*`, models
  (`Cart`, `Address`, `Delivery`, `StoreFollow`, `Favorite`, …),
  views (`home`, `product`, `cart`, `checkout`, `orders`, `order`, `store`, `profile`, `messages`, `notifications`),
  full cart/checkout/order routes.
- Seller Center from seller app + admin backend (`/seller/*`).
- Admin Center (`/admin/*`).

## Single-backend rules (like Shopee / Lazada)
- Landing: `GET /` → `resources/views/landing.blade.php` (also saved as `index.html` at root and `public/index.html`).
- Register (`GET|POST /register`) → ALWAYS creates `role=buyer`, auto-verified, logs in, → `/shop`.
- Login (`GET|POST /login`) → role redirect:
  - `admin` / `is_admin` → `/admin/dashboard`
  - `seller` (approved) → `/seller/dashboard`
  - `buyer` / others → `/shop`
- Legacy `/seller/login`, `/admin/login`, `/buyer/login` still work and point to the same accounts.
- DB: `invoizdb` (`DB_HOST=127.0.0.1`, `DB_USERNAME=invoiz`, `DB_PASSWORD=ren123`) — see `.env`.

## Run
```powershell
cd "C:\Users\LENOVO\OneDrive\Desktop\Invoiz e-commerce\invoiz-integrated"
php artisan migrate --force
php artisan db:seed --class=AdminSeeder   # if present; admin = cmiavenus@gmail.com
php artisan serve --host=127.0.0.1 --port=8000
```
Open: `http://127.0.0.1:8000/` (landing), `/register` (buyer), `/login` (role redirect),
`/shop`, `/cart`, `/orders`, `/seller/dashboard`, `/admin/dashboard`.

## Notes
- `User` model fillable merged (`name` + `first_name/last_name` + `otp` + `account_status/status`) + `displayName()`, `isBuyer/isSeller/isAdmin`.
- New non-destructive migration: `2026_10_04_000001_unified_buyer_columns.php` (adds missing buyer cols, `carts`, `addresses`).
- `bootstrap/app.php`: guests → `/login`, users → `/home` (→ `/shop`).
- Static preview: open `index.html` directly; live version is served by Laravel at `/`.

## Exact seller app (original look + functions)

- Folder: C:\Users\LENOVO\OneDrive\Desktop\Invoiz e-commerce\invoiz-seller (exact copy of the original seller app).
- Same invoizdb, same APP_KEY + SESSION_COOKIE=invoiz_session (set in both .env files).
- Login on :8000 as a dual account -> /choose -> Continue as Seller creates a single-use sso_tokens row and hands off to :8100/sso/{token}, which signs you into the exact seller dashboard.
- Logout anywhere deletes all of the account's sessions, so both apps log out and return to the :8000 landing page.
- Run both: php artisan serve --port=8000 here, php artisan serve --port=8100 in invoiz-seller.

