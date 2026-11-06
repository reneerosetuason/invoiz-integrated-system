# Hub transfer driver feature merge verification

Source: `origin/feature/hub-transfer-driver-application` at `197a727`.
Target before merge: `main` at `e072ef4`.
Recovery branch: `backup/pre-hub-transfer-driver-merge-20261106`.

## Integration

- Kept the existing buyer/logistics email separation rules alongside the incoming logistics role checks.
- Kept the rider app's iOS presentation, remittance acceptance, and current delivery workflow.
- Routed restored sessions and successful login through `homeForAuth`, so staff open the logistics scanner while delivery riders and hub drivers keep their respective dashboards.
- Added the required applicant phone when provisioning the hub driver's linked `users` row. This was a reproducible approval failure against the current schema, whose `users.phone` column is required.
- Reject marketplace email reuse again at approval time, including pending applications created before a buyer/seller registered that email. No existing accounts are converted or deleted by the merge.
- Made the new shared email guard tolerate the deliberately partial SQLite test fixtures; the live and MySQL sandbox schema retain all role checks.
- Corrected the incoming scanner's header colors and made the read-only message scroll at larger text sizes. Existing mobile styling and controls remain in place.

## Database

The feature introduces no migrations. The connected shared database is `invoizdb`; its 102 tables include the feature's users, drivers, applications, facilities, companies, deliveries, delivery status history, and Sanctum token tables. The feature's MySQL tests ran against a schema-only copy of these exact definitions, including their required columns and constraints.

No migrations, seeders, account edits, or test writes were run against the live database. It remained active during verification, so live row counts increased while users continued working. Tests used an isolated local MariaDB instance bound to `127.0.0.1:3308`, database `invoiz_ecommerce_sandbox`, and transaction rollback. No live customer rows were copied into that database.

The existing migration history predates some imported tables, so historical `Pending` entries must not be treated as permission to rebuild this database. Do not use `migrate:fresh` against the live connection. A broad model-fillable audit also reports the pre-existing `OrderItem.variant_id` declaration; the actual order table uses `product_variant_id`. This is unchanged by the feature and is not a dependency of its scanning or driver provisioning paths.

## Validation

- MySQL: 20 logistics mobile scanning and email/approval protection tests, 111 assertions.
- MySQL: 46 hub-driver application, management, pairing, and courier workflow tests, 277 assertions.
- In-memory SQLite: 41 existing email separation, center application, interhub/hub/admin remittance tests, 292 assertions.
- Rider Flutter: full suite, 417 tests passed, including the incoming models/login/session routing and scanner layouts at small widths, larger text, and landscape.
- PHP syntax: all changed PHP files passed.
- Laravel route registration: all three `/api/logistics/scan` routes registered; Blade compilation passed.
- Flutter analyzer: no errors; existing warning/info diagnostics remain (27 total).

Commands:

```powershell
php artisan test -c phpunit.mysql.xml --compact tests/Feature/LogisticsMobileScanApiTest.php
php artisan test -c phpunit.mysql.xml --compact tests/Feature/HubTransportDriverApplicationWorkflowTest.php tests/Feature/HubTransportDriverManagementTest.php tests/Feature/HubTransferDriverPairingWorkflowTest.php
php artisan test --compact tests/Feature/AccountEmailSeparationTest.php tests/Feature/CenterApplicationFlowTest.php tests/Feature/InterHubRemittanceAcceptanceTest.php tests/Feature/HubRemittanceAcceptanceTest.php tests/Feature/AdminRemittanceAcceptanceTest.php
php artisan route:list --path=api/logistics/scan --except-vendor
php artisan view:cache
# From rider_mobile using the project's Flutter 3.44.5 SDK:
flutter test --no-pub
flutter analyze --no-pub --no-fatal-infos --no-fatal-warnings
```
