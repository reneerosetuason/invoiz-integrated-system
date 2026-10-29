# INVOIZ Rider App

The Flutter Rider App is a separate client of the integrated Laravel
application. Its source lives in `rider_mobile`; it communicates with MySQL
only through Laravel's authenticated logistics API.

## Project structure

- `lib/core` — API configuration, HTTP client, token storage, utilities, and
  shared UI theme.
- `lib/models`, `lib/providers` — API models and app/session state.
- `lib/screens`, `lib/widgets` — login, dashboard, delivery workflow, scanner,
  profile, messages, settings, and tutorial UI.
- `lib/services` — API requests and local tutorial/deep-link services.
- `android` — Android app configuration and `invoizrider://` intent filters.
- `test` — unit and widget coverage for API/session behavior, workflows,
  tutorial, deep links, and UI.

## API configuration

The API base URL must include `/api/logistics`. The tracked template is
`.env.example`; create the Flutter dotenv asset before running or building:

```powershell
Copy-Item .env.example .env
```

Leave `API_BASE_URL` empty for the built-in development address for the current
platform, or set it in `.env` for the target environment:

- Android emulator: `http://10.0.2.2:8000/api/logistics`
- Physical Android device: `http://<PC-LAN-IP>:8000/api/logistics`
- Web or desktop on the Laravel host: `http://127.0.0.1:8000/api/logistics`
- Deployment: `https://<deployment-host>/api/logistics`

The URL is not secret; do not put passwords, API keys, or signing credentials
in `.env.example`. A non-empty `.env` value overrides the platform default;
`--dart-define=API_BASE_URL=...` is used when `.env` leaves it empty. `.env` is
ignored by Git and should be configured locally or for each build environment.

For Android emulator testing, Laravel must listen on a host-reachable
interface. From the Laravel repository, run
`php artisan serve --host=0.0.0.0 --port=8000`; the emulator reaches the host
through `10.0.2.2`. Keep this limited to a trusted development network and
allow the port only on the private network if Windows Firewall prompts.
Physical Android devices can use the development computer's LAN IP with both
devices on the same trusted network. Alternatively, an ADB reverse connection
(described below) makes the computer's Laravel server available at the phone's
`127.0.0.1:8000`. Without that reverse connection, localhost refers to the phone.

Run the app and checks from this directory:

```powershell
flutter pub get
flutter analyze
flutter test
flutter run
```

The Android emulator/device must be able to reach the Laravel host. The
development API URL uses HTTP; deployed APIs should use HTTPS. The Android
release build currently uses debug signing and is not ready for APK
distribution.

## Laravel contract

The client uses the existing `/api/logistics` API. Important service paths
relative to that base include:

- Authentication: `POST /login`, `POST /logout`; rider password reset and
  application/email-verification routes.
- Rider profile/work state: `GET|PATCH /rider/profile`,
  `PATCH /rider/password`, `PATCH /rider/status`, `GET /rider/dashboard`.
- Delivery workflow: list/detail/tracking lookup, accept, pickup, status,
  sorting-center handoff/pickup, completion, failure, reattempt, and return.
- The delivery-completion screen requires a live photo and sends it as
  multipart `photo`; it also sends the buyer OTP only when required and the
  COD amount when required by the API. The Laravel completion endpoint still
  accepts photo-less requests from other clients, so the mobile photo rule is
  currently enforced by the Flutter UI rather than server-side.
- Earnings, history, remittances, notifications, GPS location, and rider
  conversations/messages use their corresponding `/rider/*` routes.
- Application and lookup services use `/rider/apply`, `/center/apply`,
  `/vehicle-types`, and `/address/*`.

Laravel Sanctum bearer tokens authenticate rider requests. Backend role checks
and delivery ownership checks remain authoritative; API paths and request
fields are documented and validated by the Laravel routes/controllers.

The Laravel lost/damaged exception endpoint exists, but the existing Rider App
has no corresponding service or screen flow; no new exception workflow was
invented as part of this integration.

The app preserves the five-step rider tutorial per rider on-device and can
replay it from Settings. Android handles `invoizrider://login` and
`invoizrider://reset-password` links.

## Shared project database

The repository root Laravel app owns the database and migrations. The SQL
files under `database/` in this client are historical reference only; do not
import them into the integrated database or create a second rider database.
The buyer client stays in `buyer_mobile`, beside this `rider_mobile` client.
Use Flutter 3.44 or later with Dart 3.12.2 or later for this app; the buyer
client may use its existing older SDK independently.

With an authorized ADB-connected Android phone, `adb reverse tcp:8000 tcp:8000`
allows `http://127.0.0.1:8000/api/logistics` to reach the same local Laravel
backend as the buyer app. Include the entire `/api/logistics` prefix.

Build a development APK with the same arrangement:

```powershell
.\scripts\Build-Android.ps1 -FlutterCommand 'C:\path\to\flutter-3.44\bin\flutter.bat' -ApiBaseUrl http://127.0.0.1:8000/api/logistics
```

Keep `API_BASE_URL` empty in the local rider `.env` when using this script's
build-time URL; a non-empty dotenv value takes precedence in the original
client. This file is a Flutter configuration asset, not the Laravel root
`.env`; never put database credentials or secrets in the client asset.

## VS Code SDK and TECNO debugging

The rider app needs Flutter 3.44 / Dart 3.12.2 or later. The older buyer SDK
at `C:\flutter` can report red editor errors when used for the rider project.
This workspace has a separate SDK at `.tools/flutter-rider-3.44.5`, excluded
from Git. The Android build helper automatically uses it when available.
The buyer SDK installation and global VS Code settings are kept intact.

Local settings in the repository root `.vscode/settings.json` select the rider
SDK for this editor window. After changing an SDK, run **Developer: Reload
Window** once if old diagnostics remain. Do not change the application's
functions to work around diagnostics from an incompatible SDK.

This computer's local **INVOIZ Rider (TECNO)** launch profile opens
`rider_mobile/lib/main.dart`, selects the connected TECNO, and forwards port
8000 before launch. Keep the shared Laravel backend running. Select that
profile in Run and Debug, then press F5. The phone must remain authorized in
USB or Wireless debugging. These local device settings are excluded from Git.
