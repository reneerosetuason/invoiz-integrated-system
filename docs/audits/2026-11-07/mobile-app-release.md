# Android app update - 7 November 2026

The reported phone versions were Buyer 1.0.0 and Rider 1.0.1. New release APKs have been built from the current buyer and rider/logistics source and published on the website landing page.

| App | New version | Build | Download size | Package |
| --- | --- | --- | --- | --- |
| Buyer | 1.0.2 | 3 | 51.9 MB | `com.invoiz.invoiz` |
| Rider / inter-hub driver / logistics | 1.0.3 | 5 | 71.9 MB | `com.invoize.invoize_rider` |

## Updating a phone

1. Open [the website downloads](https://invoiz.store/#downloads) on the phone. The cards display the versions above and the actual APK sizes.
2. Download the appropriate app and open the APK. Allow installation from the browser if Android requests it.
3. Choose **Update**, keeping the existing app and its data. There is no need to uninstall or clear storage.
4. Check Android Settings -> Apps -> App info for the new version. Rider also displays its installed version under **Settings -> App Version**.

Existing phones do not silently update when a website APK changes. No phone was connected to ADB during this release, so installed copies were not replaced or tested on a physical device.

## Included return/refund functions

- Buyer: request a return/refund from an order, review the agreement and fees, accept or dispute the proposal, verify a private refund receiving account, submit an agreed funding deposit with proof, preview images, confirm an actual refund and confirm a replacement when applicable.
- Rider: assigned return collection with photographic handover evidence; return payment/remittance account details and explicit receipt acceptance.
- Inter-hub drivers and logistics users: access to **Return payments & earnings**, including remittance acceptance. Return service earnings require the recipient's confirmation.
- All use the existing shared Laravel backend and settlement ledgers. Buyer deposits, physical returns, refund-only resolutions, exchanges, seller/admin decisions and manual transfer verification retain their existing rules.

For the detailed controls and financial flow, see the [return/refund UI guide](../../flows/return-refund-ui-guide.html). This release packages the existing implementation; it does not execute customer refunds or change financial rules.

## Release checks

- Both universal Android release builds succeeded for ARM32, ARM64 and x86_64, using `https://invoiz.store/api` and `https://invoiz.store/api/logistics`.
- Android package IDs, version names and build numbers were read from the produced APKs.
- Android signature verification passed. The certificate matches both the immediately preceding website APKs and the older pre-return-settlement backups, allowing an in-place update.
- Full local SHA256 checks match the build artifacts and published APKs. `public/downloads/manifest.json` records version, build, bytes, package and checksum. Landing links use those checksums to avoid previously cached installers.
- Public HEAD requests returned HTTP 200 with the exact new file sizes. Public range requests returned HTTP 206, and the first 65,536 bytes of each file match its local build. The live landing page shows both new version labels and checksum-based links.
- Full public-file checksum downloads timed out on the slow tunnel; a complete remote checksum comparison and physical-device installation are not claimed.
- ReturnSettlementTest, ReturnWorkflowTest, SellerReturnVisibilityTest and BuyerMobileIntegrationTest: **96 passed, 814 assertions**.
- Buyer return/agreement widget tests: **9 passed**. Rider return/remittance widget tests: **8 passed**.
- The configured database has all three return settlement/funding/allocation tables and the recorded settlement migration. No schema or customer data changes were required.
- Download cards passed browser checks at 320, 390, 768 and 1366 pixels. Phone and desktop screenshots were visually reviewed. Existing card styling was retained.
- Blade compilation, PowerShell script parsing and `git diff --check` passed.

An additional broader logistics/download test run had 20 failures, including missing SQLite fixtures in tests that expect an existing transactional database and older page-copy expectations. These were not treated as successful integration checks. The focused return and mobile suites above passed.

## Publishing the next build

Increment each app's `pubspec.yaml` version/build, build its release APK, then run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/Publish-MobileApps.ps1
```

The publisher checks both APKs before replacement, rejects changed signing keys or non-increasing build numbers, backs up previous installers privately, replaces the downloads and writes their manifest. This release's backups are in `storage/app/backups/mobile-release-20261107-072811`.

APK binaries and build output are Git ignored. A separate server deployment must receive the APK files as well as the manifest and website changes.

Follow-up: rider/logistics **1.0.4 (build 6)** fixes a duplicated scanner URL prefix and was installed on the connected phone. See [the scanner fix and device verification](logistics-mobile-scanner.md). To publish only that app, use `scripts/Publish-MobileApps.ps1 -AppKind rider`.
