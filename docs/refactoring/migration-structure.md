# Single migration directory

The selected option B keeps every migration directly in Laravel's default
`database/migrations/` directory. The previous 46-file logistics subfolder has
been flattened into that directory alongside the existing 67 root migrations.
There are now 113 migration files and no migration subdirectories.

```text
database/
  migrations/
    0001_01_01_000000_create_users_table.php
    ... all 113 migrations in filename order ...
```

No custom migration-path registration is needed. The existing service provider
and migration bodies are unchanged. Existing migration filenames, timestamps,
class definitions, and basename identifiers used by Laravel's migration history
are preserved. The `LogisticsMergeTest` direct migration include and the audit
reference now point to the file's new location.

This change makes the previously undiscovered logistics migration files visible
to normal Laravel migration commands. No `up()` or `down()` methods were run,
and no database history or application data was changed. Applying historical
pending migrations is a separate database operation.

## Verification

- Laravel's installed migrator discovers exactly the original combined set of
  113 migration identifiers through the default directory.
- Discovery sorts that complete set by its unchanged filenames.
- SHA-256 comparison confirms every file's contents are identical to its
  pre-move version, including all 67 files already at the root.
- All 113 migration PHP files and the updated feature-test file pass `php -l`.
- The feature test's direct migration include resolves to an existing file.
- No migration filename collisions or nested migration directories remain.
- No database migrations were executed. Database-level historical SQL
  compatibility was not tested as part of this structural move.

## Moved files

Each file below moved from the former logistics subfolder to the main
`database/migrations/` directory, retaining the same filename:

```text
2026_01_01_000000_create_deliveries_base.php
2026_01_01_000001_extend_users_table.php
2026_01_01_000002_create_riders_table.php
2026_01_01_000003_create_rider_applications_table.php
2026_01_01_000004_extend_deliveries_table.php
2026_01_01_000005_create_delivery_status_logs_table.php
2026_01_01_000006_create_notifications_table.php
2026_01_01_000007_create_conversations_and_messages_table.php
2026_01_01_000008_create_logistics_settings_table.php
2026_08_19_000001_add_rider_rider_columns.php
2026_08_19_000002_add_delivery_rider_columns.php
2026_08_19_000003_create_delivery_items_table.php
2026_08_19_000004_create_delivery_proofs_table.php
2026_08_19_000005_create_delivery_failures_table.php
2026_08_19_000006_create_rider_earnings_table.php
2026_08_19_000007_create_rider_locations_table.php
2026_08_19_000008_create_rider_notifications_table.php
2026_08_25_000001_logistics_hardening.php
2026_08_25_000002_vehicle_types_and_verification.php
2026_08_26_061007_add_package_fields_to_deliveries_table.php
2026_08_26_063450_create_rider_application_documents_table.php
2026_08_26_073337_add_message_fields_and_attachments.php
2026_08_31_000001_create_logistics_centers_table.php
2026_08_31_000002_create_service_areas_table.php
2026_08_31_000003_add_center_to_riders_and_users.php
2026_08_31_000004_add_delivery_processing_fields.php
2026_08_31_000004_add_rider_application_provisioning_columns.php
2026_08_31_000005_create_transactions_table.php
2026_09_01_000001_add_unique_delivery_to_transactions.php
2026_09_03_085552_add_application_profile_and_ownership_fields_to_rider_applications_table.php
2026_09_04_000000_add_disk_to_message_attachments.php
2026_09_06_000001_create_rider_email_verifications_table.php
2026_09_11_000001_add_rider_id_to_logistics_conversations_table.php
2026_09_11_000002_add_personal_fields_to_rider_applications_table.php
2026_09_11_000003_create_psgc_tables.php
2026_09_11_000004_create_logistics_center_applications_table.php
2026_09_12_000001_create_pickup_requests_table.php
2026_09_12_000002_add_dispatched_at_to_deliveries_table.php
2026_09_13_000001_add_sorting_center_handoff_fields_to_deliveries_table.php
2026_09_15_000001_add_wizard_error_steps_to_logistics_center_applications_table.php
2026_09_16_000001_add_preferences_to_logistics_settings_table.php
2026_09_16_000002_create_login_histories_table.php
2026_10_05_000001_add_service_area_id_to_users.php
2026_10_07_000001_create_password_reset_tokens_table.php
2026_10_07_000002_add_reattempt_requested_at_to_deliveries_table.php
2026_10_20_000003_add_exception_statuses_to_deliveries_table.php
```

The [complete move manifest](migration-structure-manifest.json) records old/new
paths, file hashes and verification results.
