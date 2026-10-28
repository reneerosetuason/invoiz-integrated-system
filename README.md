# INVOIZ

Laravel application for the buyer marketplace, Seller Center, administration,
and logistics/rider operations.

## Local development

Requires PHP 8.2+, Composer, Node.js/npm, and the database configured in `.env`.
For a new checkout, copy `.env.example` to `.env`, configure the database and
mail settings, and generate an application key with `php artisan key:generate`.
Keep an existing installation's `.env` and application key.

From the project root:

```powershell
composer install
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000/`. The local Cloudflare tunnel command remains in
`run.txt`. Database migrations and seeders live in `database/`; apply them only
as required by the installation being set up.

## Shared backend and database

Buyer, seller, admin, logistics and rider API requests use the same Laravel
application and the single default database connection configured by `DB_*`
in `.env`. Accounts share `users`; permissions still depend on the role and
approved seller/rider/center profiles. Role folders organize the code.

Verify the current installation without changing data:

```powershell
php artisan invoiz:check-backend
```

The Flutter buyer client is in [`buyer_mobile`](buyer_mobile/README.md). Its
18 screens use this backend, with no separate server or database. The browser
preview is served at `/buyer-app/` after running its web build script. Mobile
devices must use the same reachable website origin's `/api`.
See [buyer app integration](docs/buyer-mobile-integration.md) and
[shared backend verification](docs/shared-backend.md).

## Project organization

Views, public assets, routes and controllers are grouped by buyer, seller,
logistics, admin and shared ownership. Each role uses the shared navbar and
sidebar templates under `resources/views/shared/navigation/`.

All database migrations live directly in `database/migrations/`, using Laravel's
default discovery and filename ordering. See the [migration organization record](docs/refactoring/migration-structure.md)
for the logistics migration moves.

- [Role, authentication and authorization reference](README-INTEGRATED.md)
- [Folder structure and original refactor](docs/refactoring/file-structure.md)
- [Unused-file cleanup inventory](docs/refactoring/cleanup.md)
- [Historical database integration notes](docs/DATABASE_CHANGES.md)

## Verification

```powershell
php vendor/phpunit/phpunit/phpunit --filter "ViewStructureTest|BuyerWebShoppingTest|CommerceRegressionTest|SharedBackendTest|BuyerMobileIntegrationTest"
npm run build
```

The focused tests use isolated fixtures. Most other feature tests require the
dedicated MySQL test database configured by `phpunit.mysql.xml`; do not point
tests at the development or production database. See the integration reference
for the database-backed suite.
