**Shared backend and database**

Buyer, seller, admin, logistics staff and riders use one Laravel application.
Both web and API routes are loaded by `bootstrap/app.php`. The role folders
separate code ownership and presentation; they do not create separate servers,
account stores or databases.

| Area | Entry point | Backend/data source |
| --- | --- | --- |
| Buyer | `/shop`, `/login`, `/api/login`, buyer `/api/*` routes | Shared Laravel application and default database |
| Seller | `/seller/*` | Same application/database; approved seller profile required |
| Marketplace admin | `/admin/*`, `/api/admin/*` | Same application/database; administrator permission required |
| Logistics staff/admin | `/logistics/*` | Same application/database; logistics eligibility/center scope enforced |
| Rider | `/api/logistics/*`, rider web entry | Same application/database; approved rider profile required |

**Configuration and deployment**

- Configure the default connection once using `DB_CONNECTION`, `DB_HOST`,
  `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (or the shared `DB_URL`)
  in the server's `.env`. Keep credentials on the server. Alternative driver
  definitions in `config/database.php` are supported driver choices, not role databases.
- All application models inherit that connection. One `App\Models\User`
  provider and `users` table supply account identities; seller/rider records
  extend those identities. Sanctum stores API tokens on the shared connection.
- Browser role pages and their actions use this application's routes. The old
  `INVOIZ_BUYER_URL`, `INVOIZ_SELLER_URL` and `INVOIZ_MARKETPLACE_URL` settings
  are no longer used. An authenticated marketplace account visiting the
  logistics root returns to `/shop` or `/home`; its session is retained.
  Logistics login and protected operations retain their existing eligibility checks.
- Publish one application origin for all clients. If several Laravel workers
  or server instances are deployed, give each the same database configuration
  and compatible shared session/cache configuration. Run the check on each instance.
- The buyer mobile source is now integrated in `buyer_mobile`; its single
  `INVOIZ_API_BASE_URL` setting must use the deployed origin's `/api`. Its web
  preview shares the Laravel origin at `/buyer-app/`. See
  [buyer integration](buyer-mobile-integration.md). External rider app source
  is still absent; its installed API address cannot be verified here. The rider
  deep link only opens that app; it does not configure its API/database.
- Automated tests intentionally use a separate, isolated database. They must
  never share live customer data or run schema resets against the application database.

**Repeatable verification**

```powershell
php artisan invoiz:check-backend
php vendor/phpunit/phpunit/phpunit tests/Feature/SharedBackendTest.php --do-not-cache-result
```

The read-only command verifies that every concrete model under `app/Models`,
the configured Sanctum token model, authentication providers and selected
database-backed session/cache/queue stores use the same PDO connection. It
also checks the presence of `users`, `sellers`, `riders`, `products`, `orders`,
`order_items`, `deliveries`, `remittances`, `payments`, and `personal_access_tokens`.
A split connection, missing table, incompatible account provider or connection
failure produces a nonzero exit status. No records or migrations are written.
The check confirms connection/table alignment, not full schema or every business rule.

On 28 October 2026, the running configuration resolved to MySQL database
`invoizdb`. All **72 application models** and the API token model shared the
default connection; all ten core tables were present. Web/API role routes
were registered in this Laravel application's router, and no role-specific
database overrides or hard-coded alternate backend origins were found in the
application clients in this checkout.

The isolated regression coverage checks all seven stored roles against the
same account provider, shared order/item/delivery relationships, local
marketplace redirects, preserved logistics access restrictions, and rejection
of split session storage, a different account provider, and missing role tables.
This work does not repair the separately documented security/accounting
findings in `docs/audits/2026-10-28/security-and-functionality-review.md`.
