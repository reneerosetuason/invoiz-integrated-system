# Logistics and rider database verification — 2026-11-04

Verified and repaired the database actually selected by Laravel: local MySQL
`127.0.0.1:3306/invoizdb`. The root `.env` was not changed.

## Findings and applied repairs

The original rider integration's tables and nullable profile repair were already
present. Eight migrations from subsequent merged rider/hub transport work were
pending, and their schema changes were actually missing. Applied only these
existing migrations using explicit `php artisan migrate --path=... --force`
arguments, in filename order:

```text
2026_11_01_000001_add_application_type_to_rider_applications_table
2026_11_01_000002_add_hub_transport_fields_to_rider_applications_table
2026_11_01_085927_make_delivery_id_nullable_and_add_trip_unique_to_hub_transfer_earnings
2026_11_01_100000_create_regions_and_enhance_psgc_tables
2026_11_01_110000_update_hub_transport_driver_and_application_schema
2026_11_01_130000_enhance_hub_transport_fleet_and_incidents
2026_11_01_140000_create_vehicle_maintenance_records_table
2026_11_02_170000_add_avatar_to_hub_transport_drivers_table
```

These created `regions`, `vehicle_incidents`, and `vehicle_maintenance_records`;
added 28 columns across the existing application, driver, fleet, earnings, and
address tables; added their declared indexes and foreign keys; permitted
trip-level earnings without a delivery; and added the unique trip/recipient-role
index. Application vehicle type is now nullable, and vehicle ownership accepts
the values used by the merged workflow. The two additional vehicle types are
`l300` and `light_truck`.

Ran the existing `PsgcSeeder` against the bundled local JSON files to populate
the address dropdowns. It upserts by geographic code and preserves existing row
IDs. This intentionally refreshes matching geographic reference fields from
the bundled data. No demo/account/order seeders or historical SQL imports ran.
Invalidated only `psgc_local:*` cache keys for the affected address paths.

| Reference table | Before | After | Bundled codes missing afterward |
| --- | ---: | ---: | ---: |
| regions | absent | 18 | 0 |
| provinces | 7 | 83 | 0 |
| municipalities | 12 | 1,634 | 0 |
| barangays | 24 | 42,048 | 0 |
| vehicle_types | 4 | 6 | — |

The bundled files contain 82 provinces and 42,046 barangays. Additional legacy
reference rows were retained rather than deleted.

## Verification

- Database tables increased from 95 to 98. No existing table or column was
  removed. Every pre-existing table's row count was preserved or increased;
  business table counts were unchanged. Other count increases were the address
  references, vehicle types, and eight migration-history entries.
- All 47 tables and every column in `rider_mobile/database/schema.sql` exist in
  the shared database. This historical SQL was inspected, never imported.
- No missing fillable columns remain in the current rider, logistics, hub
  transport, incident, maintenance, or address models.
- Checked all single-column foreign keys in the resulting schema: zero
  orphaned references.
- Exercised the logistics address controller against the real database:
  18 regions, 53 Cebu province cities/municipalities, 80 Cebu City barangays.
  All six vehicle types are active.
- `php artisan invoiz:check-backend` passed: application models and API tokens
  share the configured database and the core tables exist.
- Repeating the explicit eight-path migration command reports nothing to
  migrate; Laravel recorded all eight successfully.
- Isolated in-memory SQLite tests `RiderMobileSchemaRepairTest` and
  `LogisticsVehicleCategoryTest` passed: 2 tests, 16 assertions. The installed
  test runner emits a PHP 8.5 dependency deprecation. No PHPUnit tests ran
  against `invoizdb`; full rider/driver workflow testing was not performed.

## Backup and local evidence

Before any database changes, created a full logical backup with
`mysqldump --single-transaction --routines --triggers --hex-blob`:

```text
storage/app/private/logistics-before-repair-20261104-105333.sql
701277 bytes
SHA-256: 3a1244bd22c69c768a51f2e634acf9b51dc79c0c74de5e24b334e233b73988fa
```

This private, ignored backup contains application data and is not committed.
Metadata/count snapshots and verification results are retained locally at
`storage/app/logistics-database-{before,after,verification}.json`. Temporary
audit, backup, and verification scripts are in the ignored `.tools` directory.

## Remaining scope notes

Older migrations still appear pending because this database contains imported
schema without corresponding history entries. Do not infer missing tables from
those pending labels or replay every historical migration. No migration history
was fabricated, and no fresh/reset/rollback command ran.

The broader model scan also found two unrelated marketplace/account differences:
missing `account_deletion_requests` and `order_items.variant_id`. Those are
outside the logistics/rider integration and were not changed. This report
verifies the configured local database, not any separately deployed database.
