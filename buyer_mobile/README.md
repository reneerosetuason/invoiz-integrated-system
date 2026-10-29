**INVOIZ buyer app**

This Flutter client uses the Laravel application at the repository root and
its shared database. Do not start a second backend or import the old SQL dump.
All 18 screens, the shared layout/theme/logo, Android/web projects and the
dependency lockfile were retained from the supplied source.

New mobile buyers verify their email and wait for approval on the website's
**Admin → Buyers** page. Existing approved website accounts use their existing
email/password. Products, carts, addresses, orders, messages and permissions
come from the same backend as the website.

**Folder guide**

This app lives at `C:\Invoiz\buyer_mobile`. Its source is organized as follows:

```text
buyer_mobile/
  lib/
    main.dart       App entry point
    config.dart     Shared API address configuration
    theme.dart      App colors and styling
    screens/        Buyer pages and flows
    services/       API, authentication and local history
    models/         Data objects returned by the API
    widgets/        Reusable layout and UI components
  assets/           Logo and app assets
  android/          Android platform project
  web/              Browser platform project
  test/             Flutter tests
  scripts/          Web and Android build commands
  api-contract.json Shared-backend endpoint reference
  pubspec.yaml      Flutter dependencies and app settings
```

`build/`, `.dart_tool/` and Android tool caches are generated and Git ignored.
The Laravel backend stays in the repository root. Mobile API routes keep
their existing `/api/mobile/buyer/*` addresses.

**Try it on this computer**

Keep Laravel running at `http://127.0.0.1:8000`. From `C:\Invoiz`:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File buyer_mobile/scripts/Build-Web.ps1
```

Open `http://127.0.0.1:8000/buyer-app/`. This preview shares Laravel's origin
for API calls and uploaded images. `public/buyer-app/` is generated and Git ignored.

**Android emulator**

From this folder:

```powershell
flutter pub get
flutter run
```

Android defaults to `http://10.0.2.2:8000/api`, the emulator address for this
computer's Laravel server. Debug builds permit local HTTP; release builds
should use HTTPS. Internet permission is declared for release builds too.

Build a debug APK from `C:\Invoiz`:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File buyer_mobile/scripts/Build-Android.ps1
```

Output: `buyer_mobile/build/app/outputs/flutter-apk/app-debug.apk`.
This is a debug APK, not a signed Play Store release.

**A physical phone / deployed website**

`127.0.0.1` on a phone means the phone itself. Use the reachable website HTTPS
address with `/api` added. For example, website `https://shop.example.com`
means API `https://shop.example.com/api`. That domain is an example.

```powershell
# Run from C:\Invoiz; replace the example with the actual website address.
powershell -NoProfile -ExecutionPolicy Bypass -File buyer_mobile/scripts/Build-Android.ps1 -ApiBaseUrl https://shop.example.com/api
```

Or run from this folder:

```powershell
flutter run --dart-define=INVOIZ_API_BASE_URL=https://shop.example.com/api
```

`INVOIZ_API_BASE_URL` controls both API and storage URLs. Database credentials
stay on the server. A web build hosted by Laravel uses its current public
origin automatically. The original PSGC address-dropdown service is retained.

Only localhost was found in this installation. To find an existing public
address, look at the browser address bar when accessing the website through
Cloudflare from another device. No tunnel or domain was created here.
Changing servers requires signing in again; sessions are stored per API address.

**Reconnect the development phone**

The phone's current development APK uses `http://127.0.0.1:8000/api` through
`adb reverse`. Wireless debugging and the Laravel API are separate connections:
the phone's changing wireless debugging port connects ADB, while port **8000**
serves INVOIZ. After ADB disconnects/restarts, its reverse-port mapping may be
lost even though the website still works on the computer. No `.env` address
change is needed to restore this setup.

1. Keep Laravel running in a PowerShell terminal from `C:\Invoiz`:

   ```powershell
   php artisan serve --host=127.0.0.1 --port=8000
   ```

   If the website is already running on port 8000, keep that existing server.

2. **USB is the easiest option.** Enable USB debugging, connect the phone, unlock
   it and accept **Allow USB debugging**. From another terminal in `C:\Invoiz`:

   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File .\buyer_mobile\scripts\Connect-Phone.ps1
   ```

3. **For Wi-Fi**, keep the computer and phone on the same network. On the phone,
   enable Developer options > Wireless debugging. Use the current **IP address &
   port** from that screen, replacing the example below:

   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File .\buyer_mobile\scripts\Connect-Phone.ps1 -Address 192.168.1.232:38057
   ```

   This is the connection port, not the port in the pairing-code dialog. If this
   computer is no longer paired, first open **Pair device with pairing code**:

   ```powershell
   $adb = "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe"
   & $adb pair PHONE_IP:PAIRING_PORT
   ```

   Enter the displayed six-digit code when prompted. Return to the main Wireless
   debugging screen and run Connect-Phone with its **connection** IP/port.

The script selects an authorized phone, checks Laravel, restores
`adb reverse tcp:8000 tcp:8000`, requests `/api/categories` **from Android**, and
opens the installed app. It stores the selected phone identity only in the
Git-ignored `.dart_tool/invoiz-phone.json`. On later runs it can rediscover that
same paired phone if wireless service discovery is available; otherwise supply
the current `-Address` again. It does not connect to unrelated discovered phones,
clear app data, rebuild the APK or change backend settings. With multiple devices,
select one using `-DeviceSerial` (listed by `adb devices -l`).

For manual reconnection, after `adb connect PHONE_IP:CONNECTION_PORT`:

```powershell
$adb = "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe"
& $adb devices -l
& $adb -s PHONE_SERIAL reverse tcp:8000 tcp:8000
& $adb -s PHONE_SERIAL shell am start -W -n com.invoiz.invoiz/.MainActivity
```

Keep the backend and debugging connection running. A phone restart, ADB restart,
Wi-Fi change or disabled Wireless debugging can require another reconnect. If
the APK is rebuilt, preserve its local API address explicitly:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\buyer_mobile\scripts\Build-Android.ps1 -ApiBaseUrl http://127.0.0.1:8000/api
```

**Buyer requests on the seller Returns page**

The seller page at `/seller/returns` shows requests saved by that store's buyers.
Opening the request dialog alone does not create a record. On mobile, open a
delivered order, choose **Request return**, complete the form and tap **Send
request**. Confirm the app reports success, then refresh the correct seller's
Returns page. A network/validation error means no successful submission was
confirmed; reconnect, check the order, and retry. The app shows an existing open
request after refreshing order details, so check that before resubmitting after
an uncertain network response. The backend prevents a second open request for
the same purchased line.

**Verification**

```powershell
flutter analyze --no-fatal-infos --no-fatal-warnings
flutter test
```

The imported screens still have legacy lint/deprecation findings. From the
repository root, verify shared connections and the mobile API:

```powershell
php artisan invoiz:check-backend
php vendor/phpunit/phpunit/phpunit tests/Feature/BuyerMobileIntegrationTest.php --do-not-cache-result
```

See [integration details](../docs/buyer-mobile-integration.md) and the
[endpoint manifest](api-contract.json). Physical picker interaction, real mail
delivery and phone access need device/deployment testing. No iOS project was supplied.
