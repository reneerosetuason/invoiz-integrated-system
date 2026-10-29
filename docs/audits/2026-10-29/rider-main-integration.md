# Rider integration into main ? 2026-10-29

Local merge: `d05df1d` (parents `605278e` and `379df5e`). Remote repository:
`reneerosetuason/invoiz-integrated-system`. No push or branch deletion.

## Preservation and merge decisions

- Started on `main`, already equal to fetched `origin/main` (`605278e`).
- Reviewed the feature branch's one unmerged commit (`379df5e`). Local feature
  reference was fast-forwarded to that remote tip without force.
- Preserved 92 existing changed/untracked files, staged and unstaged binary
  patches, the original index snapshot, and their hashes outside the project.
  A reversible stash is retained as an additional recovery copy.
- Created the merge independently of the pre-existing work. Restored that work
  and its staging after the merge, then verified byte-for-byte preservation.
  Git stash normalized three files' line endings; those original bytes were
  restored from the backup after confirming no substantive difference.
- Git reported no text conflicts. No existing tracked file was deleted.
- Buyer landing view and CSS match main exactly, as requested. The optional
  rider download endpoint remains disabled unless explicitly configured; no
  download section was added to the buyer landing page.
- Included both incoming waybill layouts as explicitly requested. Fixed the
  seller preview's confirmed 360px horizontal overflow with a screen-only rule;
  printed label dimensions are unchanged.
- The root `.env` was not edited. Existing buyer and rider application designs,
  routes, API fields, and role/ownership boundaries were retained.

## Files and functionality

| Files | Main retained | Feature / integration retained |
| --- | --- | --- |
| `rider_mobile/**` | Buyer client stays in `buyer_mobile` | Complete Flutter rider frontend moved from incoming `mobile/rider` to a sibling folder; core, models, providers, services, screens, widgets, tests, Android and web sources preserved |
| `app/Http/Controllers/Logistics/Api/RiderLocationController.php` | Existing endpoint and response | Location delivery ownership validation |
| `app/Http/Controllers/Logistics/DeliveryController.php` | Existing delivery workflow | Trim/uppercase scanned tracking; same-origin relative result URLs |
| `app/Http/Middleware/EnsureActiveWebAccount.php` | Account restrictions | Role-specific inactive-account redirects |
| `app/Http/Middleware/Logistics/EnsureLogisticsStaff.php` | Staff authorization | JSON 403 responses for API-style logistics requests |
| `resources/views/logistics/deliveries/scan.blade.php`, `public/assets/logistics/js/deliveries/scan.js` | Current scanner layout and button theme | Manual entry / camera fixes and same-origin scan requests |
| `resources/views/logistics/deliveries/show.blade.php`, `public/assets/logistics/{css,js}/deliveries/show.*` | Existing controller data and actions | Incoming logistics waybill and print isolation |
| `resources/views/seller/orders/waybill.blade.php`, `public/assets/seller/{css,js}/orders/waybill.*` | Seller data/ownership logic | Incoming seller waybill, tracking fallback and codes; mobile preview width fix |
| `routes/web.php`, `config/services.php`, `.env.example` | All existing routes/configuration | Optional, disabled-by-default verified rider installer endpoint and documented settings; actual environment unchanged |
| `database/migrations/2026_10_29_000004_repair_rider_mobile_profile_columns.php` | Existing tables and records | Guarded additive repair for four genuinely missing nullable columns |
| `database/test-migrations/2026_10_29_000001_repair_missing_logistics_test_tables.php` | Production migration path untouched | Incoming test-only, explicitly guarded repair, not run against the shared database |
| `tests/Feature/{LogisticsDeliveriesScanEndpoint,LogisticsWaybillPrintLayout,RiderAppDownloadSection,RiderLocationOwnership,SellerWaybillLayout}Test.php` | Main models and buyer landing behavior | Incoming regression checks; seller fixture updated for soft deletes and courier pickups |
| `tests/Feature/{DeliveryOtp,RiderDeliveryCompletion}Test.php` | All access and payment assertions | Buyer fixtures meet existing approval rules; COD/prepaid fixture uses separate orders to respect unique delivery order IDs |
| `tests/Feature/RiderMobileSchemaRepairTest.php` | Existing profile values | Isolated repair preservation / nullable-field / retry checks |
| `rider_mobile/scripts/Build-Android.ps1`, rider READMEs, `README-INTEGRATED.md` | One shared Laravel backend | Organized setup and build instructions; historical SQL is reference, never a second backend/database |

Removed a duplicate dotenv asset entry and cleaned imported whitespace. No
rider dependencies were upgraded: the incoming lockfile remains unchanged.

## Shared backend and database

- All 51 detected Flutter service calls resolve to registered Laravel routes
  beneath `/api/logistics`. Sanctum and existing role/ownership checks apply.
- All 86 existing tables remain; no new tables were needed. Compared the
  incoming historical schema with the real shared schema, then audited current
  rider/logistics model fields to catch newer requirements absent from that dump.
- Applied only the new guarded repair, using an explicit migration path:

```powershell
php artisan migrate --path=database/migrations/2026_10_29_000004_repair_rider_mobile_profile_columns.php --force
```

Added nullable `riders.license_number`, `riders.license_expiry`,
`riders.vehicle_registration`, and `rider_applications.license_expiry`.
No columns, records, or tables were removed, and no demo accounts were imported.
Older pending migration records were not replayed against existing tables.
The post-repair current model audit reports no missing fields.

## Verification

- Rider Flutter 3.44.5 / Dart 3.12.2: analyzer clean; all 240 tests passed.
- Buyer/seller backend regression suite: 112 tests / 959 assertions passed.
- Website production build: `npm run build` passed.
- PHP syntax: 99 logistics/controllers/middleware/routes/rider tests checked,
  no syntax errors. Blade view cache compilation passed.
- JavaScript syntax: logistics scan/show and seller waybill passed `node --check`.
- Route audit before restoring pre-existing route edits: 438 routes, no duplicate
  names or method/path pairs; final audit is recorded below.
- Browser inspected the real Blade fixture in Chromium at 360px and 1280px:
  no JavaScript errors, tracking barcode and QR present, no horizontal overflow
  after the mobile preview fix. A print PDF was generated.
- Schema repair test: existing profile values and counts survive retries;
  missing fields are nullable. Current live schema comparison shows only the
  four intended added columns, with no removed tables/columns.

The configured DB account cannot create/access a separate MySQL test schema.
Broader rider API tests therefore use an isolated temporary SQLite schema with
no customer data, translated from schema metadata and adapted for older
fixtures' omitted MySQL values. This checks application behavior, not MySQL
engine-specific compatibility. No tests use the live shared database.

## Review notes and next action

- Pre-existing buyer/cart/voucher/return work is restored but remains uncommitted,
  including its original staging. It is not silently included in this merge.
  Review and commit the desired outstanding work before approving a push.
- Rider release builds still use the incoming debug signing configuration.
  Development builds are suitable for testing; distribution requires release
  signing. No public APK download was enabled.
- Camera/GPS hardware behavior and a real rider login/delivery were not exercised
  on the phone during this merge. Flutter and backend tests cover their contracts.
- Existing rider UI enforces photo proof more strictly than the backend, and has
  no lost/damaged exception screen; these incoming limitations are documented in
  the rider README and were not expanded into unrelated feature changes.
- Review the local merge and this report, then explicitly approve any GitHub push.

## Complete merge file manifest

```text
M	.env.example
M	README-INTEGRATED.md
M	app/Http/Controllers/Logistics/Api/RiderLocationController.php
M	app/Http/Controllers/Logistics/DeliveryController.php
M	app/Http/Middleware/EnsureActiveWebAccount.php
M	app/Http/Middleware/Logistics/EnsureLogisticsStaff.php
M	config/services.php
A	database/migrations/2026_10_29_000004_repair_rider_mobile_profile_columns.php
A	database/test-migrations/2026_10_29_000001_repair_missing_logistics_test_tables.php
M	public/assets/logistics/css/deliveries/show.css
M	public/assets/logistics/js/deliveries/scan.js
M	public/assets/logistics/js/deliveries/show.js
M	public/assets/seller/css/orders/waybill.css
M	public/assets/seller/js/orders/waybill.js
M	resources/views/logistics/deliveries/scan.blade.php
M	resources/views/logistics/deliveries/show.blade.php
M	resources/views/seller/orders/waybill.blade.php
A	rider_mobile/.claude/agents/kfc/spec-design.md
A	rider_mobile/.claude/agents/kfc/spec-impl.md
A	rider_mobile/.claude/agents/kfc/spec-judge.md
A	rider_mobile/.claude/agents/kfc/spec-requirements.md
A	rider_mobile/.claude/agents/kfc/spec-system-prompt-loader.md
A	rider_mobile/.claude/agents/kfc/spec-tasks.md
A	rider_mobile/.claude/agents/kfc/spec-test.md
A	rider_mobile/.claude/settings/kfc-settings.json
A	rider_mobile/.claude/system-prompts/spec-workflow-starter.md
A	rider_mobile/.env.example
A	rider_mobile/.gitignore
A	rider_mobile/.metadata
A	rider_mobile/README.md
A	rider_mobile/analysis_options.yaml
A	rider_mobile/android/.gitignore
A	rider_mobile/android/app/build.gradle.kts
A	rider_mobile/android/app/src/debug/AndroidManifest.xml
A	rider_mobile/android/app/src/main/AndroidManifest.xml
A	rider_mobile/android/app/src/main/kotlin/com/invoize/invoize_rider/MainActivity.kt
A	rider_mobile/android/app/src/main/res/drawable-hdpi/ic_launcher_foreground.png
A	rider_mobile/android/app/src/main/res/drawable-mdpi/ic_launcher_foreground.png
A	rider_mobile/android/app/src/main/res/drawable-v21/launch_background.xml
A	rider_mobile/android/app/src/main/res/drawable-xhdpi/ic_launcher_foreground.png
A	rider_mobile/android/app/src/main/res/drawable-xxhdpi/ic_launcher_foreground.png
A	rider_mobile/android/app/src/main/res/drawable-xxxhdpi/ic_launcher_foreground.png
A	rider_mobile/android/app/src/main/res/drawable/launch_background.xml
A	rider_mobile/android/app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml
A	rider_mobile/android/app/src/main/res/mipmap-hdpi/ic_launcher.png
A	rider_mobile/android/app/src/main/res/mipmap-mdpi/ic_launcher.png
A	rider_mobile/android/app/src/main/res/mipmap-xhdpi/ic_launcher.png
A	rider_mobile/android/app/src/main/res/mipmap-xxhdpi/ic_launcher.png
A	rider_mobile/android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png
A	rider_mobile/android/app/src/main/res/values-night/styles.xml
A	rider_mobile/android/app/src/main/res/values/colors.xml
A	rider_mobile/android/app/src/main/res/values/styles.xml
A	rider_mobile/android/app/src/profile/AndroidManifest.xml
A	rider_mobile/android/build.gradle.kts
A	rider_mobile/android/gradle.properties
A	rider_mobile/android/gradle/wrapper/gradle-wrapper.properties
A	rider_mobile/android/settings.gradle.kts
A	rider_mobile/database/README.md
A	rider_mobile/database/schema.sql
A	rider_mobile/database/test_rider_account.sql
A	rider_mobile/database/updates/2026_08_27_rider_app_integration.sql
A	rider_mobile/database/updates/2026_08_27_rider_messaging_expansion.sql
A	rider_mobile/database/updates/2026_08_27_rider_operational.sql
A	rider_mobile/database/updates/2026_08_31_rider_account_provisioning.sql
A	rider_mobile/database/updates/CHANGELOG.md
A	rider_mobile/devtools_options.yaml
A	rider_mobile/docs/rider_test_coverage.md
A	rider_mobile/images/icon_foreground.png
A	rider_mobile/images/logo-nobg.png
A	rider_mobile/images/logo.png
A	rider_mobile/images/ridericon.jpg
A	rider_mobile/images/ridericon.png
A	rider_mobile/lib/core/config/app_config.dart
A	rider_mobile/lib/core/constants/app_constants.dart
A	rider_mobile/lib/core/network/api_client.dart
A	rider_mobile/lib/core/network/api_exception.dart
A	rider_mobile/lib/core/storage/token_storage.dart
A	rider_mobile/lib/core/theme/app_theme.dart
A	rider_mobile/lib/core/theme/motion.dart
A	rider_mobile/lib/core/utils/file_validation.dart
A	rider_mobile/lib/core/utils/format_utils.dart
A	rider_mobile/lib/core/utils/geolocation.dart
A	rider_mobile/lib/core/utils/input_validation.dart
A	rider_mobile/lib/core/utils/name_validation.dart
A	rider_mobile/lib/core/utils/payment_utils.dart
A	rider_mobile/lib/core/utils/vehicle_icons.dart
A	rider_mobile/lib/main.dart
A	rider_mobile/lib/mobileapp_driver.dart
A	rider_mobile/lib/models/conversation.dart
A	rider_mobile/lib/models/customer.dart
A	rider_mobile/lib/models/delivery.dart
A	rider_mobile/lib/models/delivery_item.dart
A	rider_mobile/lib/models/earning.dart
A	rider_mobile/lib/models/remittance.dart
A	rider_mobile/lib/models/rider.dart
A	rider_mobile/lib/models/rider_notification.dart
A	rider_mobile/lib/models/shop.dart
A	rider_mobile/lib/providers/auth_provider.dart
A	rider_mobile/lib/providers/delivery_provider.dart
A	rider_mobile/lib/providers/earnings_provider.dart
A	rider_mobile/lib/providers/notification_provider.dart
A	rider_mobile/lib/providers/remittance_provider.dart
A	rider_mobile/lib/providers/rider_provider.dart
A	rider_mobile/lib/screens/apply/application_status_screen.dart
A	rider_mobile/lib/screens/apply/apply_screen.dart
A	rider_mobile/lib/screens/apply/apply_widgets.dart
A	rider_mobile/lib/screens/apply_center/apply_center_screen.dart
A	rider_mobile/lib/screens/apply_center/center_application_status_screen.dart
A	rider_mobile/lib/screens/auth/forgot_password_screen.dart
A	rider_mobile/lib/screens/auth/login_screen.dart
A	rider_mobile/lib/screens/dashboard/dashboard_screen.dart
A	rider_mobile/lib/screens/deliveries/customer_delivery_screen.dart
A	rider_mobile/lib/screens/deliveries/deliveries_screen.dart
A	rider_mobile/lib/screens/deliveries/delivery_detail_screen.dart
A	rider_mobile/lib/screens/deliveries/failed_delivery_screen.dart
A	rider_mobile/lib/screens/deliveries/pickup_screen.dart
A	rider_mobile/lib/screens/deliveries/proof_of_delivery_screen.dart
A	rider_mobile/lib/screens/earnings/earnings_screen.dart
A	rider_mobile/lib/screens/earnings/history_screen.dart
A	rider_mobile/lib/screens/home/home_shell.dart
A	rider_mobile/lib/screens/messages/conversations_screen.dart
A	rider_mobile/lib/screens/messages/messages_screen.dart
A	rider_mobile/lib/screens/notifications/notifications_screen.dart
A	rider_mobile/lib/screens/onboarding/rider_tutorial_screen.dart
A	rider_mobile/lib/screens/profile/change_password_screen.dart
A	rider_mobile/lib/screens/profile/profile_screen.dart
A	rider_mobile/lib/screens/remittances/remittances_screen.dart
A	rider_mobile/lib/screens/scan/parcel_scan_controller.dart
A	rider_mobile/lib/screens/scan/parcel_tracking.dart
A	rider_mobile/lib/screens/scan/scan_parcel_screen.dart
A	rider_mobile/lib/screens/settings/settings_screen.dart
A	rider_mobile/lib/screens/splash/splash_screen.dart
A	rider_mobile/lib/services/address_service.dart
A	rider_mobile/lib/services/application_service.dart
A	rider_mobile/lib/services/auth_service.dart
A	rider_mobile/lib/services/center_application_service.dart
A	rider_mobile/lib/services/deep_link_service.dart
A	rider_mobile/lib/services/delivery_service.dart
A	rider_mobile/lib/services/earnings_service.dart
A	rider_mobile/lib/services/location_service.dart
A	rider_mobile/lib/services/message_service.dart
A	rider_mobile/lib/services/notification_service.dart
A	rider_mobile/lib/services/pod_draft_store.dart
A	rider_mobile/lib/services/remittance_service.dart
A	rider_mobile/lib/services/rider_service.dart
A	rider_mobile/lib/services/tutorial_completion_store.dart
A	rider_mobile/lib/services/vehicle_service.dart
A	rider_mobile/lib/widgets/address_fields.dart
A	rider_mobile/lib/widgets/app_transitions.dart
A	rider_mobile/lib/widgets/app_ui.dart
A	rider_mobile/lib/widgets/custom_text_field.dart
A	rider_mobile/lib/widgets/dashboard_card.dart
A	rider_mobile/lib/widgets/deep_link_broker.dart
A	rider_mobile/lib/widgets/delivery_card.dart
A	rider_mobile/lib/widgets/error_widget.dart
A	rider_mobile/lib/widgets/liquid_nav_bar.dart
A	rider_mobile/lib/widgets/loading_widget.dart
A	rider_mobile/lib/widgets/primary_button.dart
A	rider_mobile/lib/widgets/section_header.dart
A	rider_mobile/lib/widgets/stagger_in.dart
A	rider_mobile/lib/widgets/status_badge.dart
A	rider_mobile/pubspec.lock
A	rider_mobile/pubspec.yaml
A	rider_mobile/scripts/Build-Android.ps1
A	rider_mobile/test/apply_picker_test.dart
A	rider_mobile/test/apply_test.dart
A	rider_mobile/test/availability_test.dart
A	rider_mobile/test/center_application_test.dart
A	rider_mobile/test/change_password_test.dart
A	rider_mobile/test/deep_link_test.dart
A	rider_mobile/test/delivery_history_test.dart
A	rider_mobile/test/delivery_list_query_test.dart
A	rider_mobile/test/delivery_message_test.dart
A	rider_mobile/test/delivery_next_step_test.dart
A	rider_mobile/test/delivery_otp_test.dart
A	rider_mobile/test/email_verification_test.dart
A	rider_mobile/test/failed_delivery_rts_test.dart
A	rider_mobile/test/history_filters_test.dart
A	rider_mobile/test/input_validation_test.dart
A	rider_mobile/test/liquid_nav_bar_test.dart
A	rider_mobile/test/login_apply_fixes_test.dart
A	rider_mobile/test/login_ui_test.dart
A	rider_mobile/test/messaging_inbox_test.dart
A	rider_mobile/test/name_validation_test.dart
A	rider_mobile/test/parcel_scan_test.dart
A	rider_mobile/test/password_reset_test.dart
A	rider_mobile/test/payment_utils_test.dart
A	rider_mobile/test/pickup_verification_test.dart
A	rider_mobile/test/pod_proof_test.dart
A	rider_mobile/test/profile_edit_test.dart
A	rider_mobile/test/remittance_service_test.dart
A	rider_mobile/test/remittances_screen_test.dart
A	rider_mobile/test/responsive_layout_test.dart
A	rider_mobile/test/rider_profile_fields_test.dart
A	rider_mobile/test/rider_tutorial_test.dart
A	rider_mobile/test/scan_manual_entry_test.dart
A	rider_mobile/test/session_persistence_test.dart
A	rider_mobile/test/sorting_custody_test.dart
A	rider_mobile/test/status_labels_test.dart
A	rider_mobile/test/vehicle_icons_test.dart
A	rider_mobile/test/widget_test.dart
A	rider_mobile/web/favicon.png
A	rider_mobile/web/icons/Icon-192.png
A	rider_mobile/web/icons/Icon-512.png
A	rider_mobile/web/icons/Icon-maskable-192.png
A	rider_mobile/web/icons/Icon-maskable-512.png
A	rider_mobile/web/index.html
A	rider_mobile/web/manifest.json
M	routes/web.php
M	tests/Feature/DeliveryOtpTest.php
A	tests/Feature/LogisticsDeliveriesScanEndpointTest.php
A	tests/Feature/LogisticsWaybillPrintLayoutTest.php
A	tests/Feature/RiderAppDownloadSectionTest.php
M	tests/Feature/RiderDeliveryCompletionTest.php
A	tests/Feature/RiderLocationOwnershipTest.php
A	tests/Feature/RiderMobileSchemaRepairTest.php
A	tests/Feature/SellerWaybillLayoutTest.php
```
