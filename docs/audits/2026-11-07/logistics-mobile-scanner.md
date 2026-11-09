# Logistics mobile scanner URL fix

The reported error was `The route api/logistics/logistics/scan/lookup could not be found.` The received state of the parcel was not causing that error: the request never reached the scan controller.

`ApiClient` already uses a base URL ending in `/api/logistics`. `LogisticsScanService` appended another `/logistics` before all three scanner endpoints. It now uses paths relative to that base:

| Operation | Correct request |
| --- | --- |
| Refresh the operator's hub | `GET /api/logistics/scan/context` |
| Look up the scanned parcel | `POST /api/logistics/scan/lookup` |
| Confirm the scan | `POST /api/logistics/scan/confirm` |

Tracking numbers, authentication headers, hub permissions, received-state requirements, fleet-transfer custody checks and duplicate-scan handling retain their existing behavior. No parcel, custody or payment records were changed to make the scan pass.

## Delivery

- Rider/logistics APK **1.0.4 (build 6)** was built as a universal Android release and published on the landing page. It includes the previous return/refund and remittance features.
- Package: `com.invoize.invoize_rider`. Size: **75,368,090 bytes**. SHA256: `9300011b47f0f3cee6ba825eed00041c36ba136fa3446d9baa51c9ef877a2bb6`.
- Signing verification passed against the preceding website APK. The previous APK and manifest were backed up privately by the publisher.
- The public download returned HTTP 200 with the expected size. The live landing page displays version 1.0.4 (build 6) and its new checksum-based download link.
- The corrected public lookup route returned HTTP 401 to an unauthenticated request, confirming it reaches the protected route rather than the previous missing route.
- The update was installed over version 1.0.3 on the connected **TECNO KI7** without uninstalling or clearing data. Android reports version 1.0.4, build 6. The installed APK's SHA256 equals the published build, and the app reopened successfully with its existing session.
- The phone was signed in as an inter-hub driver. Its account was not switched, so a camera/lookup test under a live logistics account was not performed. Sign into a logistics account to use **Logistics Scanner**.

## Validation

- **28 Flutter tests passed**, covering scanner request URLs with the production API prefix, authentication/body preservation, received-parcel lookup and confirmation, duplicate confirmation, custody conflicts, role routing, parcel scanning and return collection/remittances.
- **10 backend scanner tests passed, 84 assertions**, covering the shared counter workflow, duplicates, received-state rules and access checks.
- Static analysis of the changed service and new regression tests: **no issues**. Dart formatting, PowerShell script parsing and `git diff --check` passed.
- No backend route alias or relaxed custody/permission rule was introduced.

The publisher now supports `-AppKind rider` so a scanner release updates only the rider artifact and preserves the buyer APK and manifest entry. The buyer download remains version 1.0.2 (build 3), with its previous checksum.
