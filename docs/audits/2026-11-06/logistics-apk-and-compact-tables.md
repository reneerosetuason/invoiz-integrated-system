# Logistics APK login and compact tables

The reported account was checked read-only. It is active, has an approved,
provisioned logistics-center application, and has a logistics-scoped mobile
token. No account, approval, password, or permission was changed.

The website's APK was older than the build containing logistics role routing.
The reported error text belongs to `EnsureRiderRole`, which correctly rejects
logistics tokens on rider-only endpoints. An old app can also leave a logistics
identity cached as a rider after an app update.

## Changes

- Publish rider app version 1.0.1, build 3, to
  `public/downloads/invoiz-rider.apk`. Its application ID and signing certificate
  match the previous website APK. The original is backed up privately at
  `storage/app/apk-backups/invoiz-rider-before-1.0.1-2.apk`.
- Add a download query version derived from the APK modification time to avoid
  reusing the old download URL after future replacements.
- On session restoration, if the rider profile endpoint rejects a legacy
  cached identity, resolve it through `/api/logistics/user`. Only a server-resolved
  logistics identity switches to logistics mode. Rider endpoint permissions
  remain intact.
- Extend the existing shared summary-table presentation to desktop screens.
  Secondary columns remain in the View dialog, and original forms and action
  links remain in their rows. Existing specialized payment tables are unchanged.
- Seller inventory keeps Product, Price, Stock, Status, Actions and View on
  desktop, with fewer columns on phones. Mobile product names get up to two
  lines instead of losing nearly all their space to the thumbnail.

## Validation

- 12 focused Flutter tests pass, including logistics login/session restoration,
  recovery of a legacy rider cache, role routing, and existing courier fallback.
- Inventory rendered from isolated SQLite fixtures and checked in Chromium at
  320, 390, 768, 1280 and 1920 px. Desktop checks include the visible sidebar.
  Table wrappers have no horizontal overflow; View exposes Category, Cost,
  Margin, Variants and Rating; original Edit and Archive actions remain present.
- JavaScript syntax check and Blade view cache pass.
- APK signing and package/version metadata checked before publication.

APK files are ignored by Git. Future builds must also be copied into the website
download directory; building into Flutter's output directory alone does not
update the website download.

## Physical-device verification

The connected Android phone was subsequently found to still have version 1.0.0,
build 1, installed at 14:59:57. Updated it in place with `adb install -r` to
1.0.1, build 3, without clearing app data. After launch, the existing session
opened Logistics Scanner and displayed the assigned Lumban hub instead of the
forbidden rider dashboard. The camera permission prompt was left for the user
to choose; no parcel scans or custody changes were performed.
