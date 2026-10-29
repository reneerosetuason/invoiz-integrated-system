param(
    [string]$Address = '',
    [string]$DeviceSerial = ''
)

$ErrorActionPreference = 'Stop'
$buyerRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$stateDirectory = Join-Path $buyerRoot '.dart_tool'
$statePath = Join-Path $stateDirectory 'invoiz-phone.json'
$adbCommand = Join-Path $env:LOCALAPPDATA 'Android\Sdk\platform-tools\adb.exe'
if (-not (Test-Path -LiteralPath $adbCommand -PathType Leaf)) {
    $installedAdb = Get-Command adb -CommandType Application -ErrorAction SilentlyContinue
    if (-not $installedAdb) { throw 'ADB was not found. Install Android SDK Platform-Tools using Android Studio.' }
    $adbCommand = $installedAdb.Source
}

function Invoke-PhoneAdb([string[]]$AdbArguments) {
    # ADB may print normal startup diagnostics to stderr. Judge its exit code,
    # rather than turning those diagnostics into terminating PowerShell errors.
    $previousErrorAction = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $lines = @(& $adbCommand @AdbArguments 2>&1 | ForEach-Object { $_.ToString() })
        $adbExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorAction
    }
    if ($adbExitCode -ne 0) { throw ('ADB failed: ' + ($lines -join [Environment]::NewLine)) }
    return $lines
}

function Get-ConnectedPhones {
    foreach ($line in (Invoke-PhoneAdb -AdbArguments @('devices', '-l'))) {
        if ($line -match '^(\S+)\s+device(?:\s|$)') { $Matches[1] }
    }
}

$saved = $null
if (Test-Path -LiteralPath $statePath -PathType Leaf) {
    try { $saved = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json } catch { $saved = $null }
}

if ($Address) {
    if ($Address -notmatch '^(\d{1,3}(?:\.\d{1,3}){3}):(\d{1,5})$') {
        throw 'Use the Wireless debugging IP address and connection port, for example 192.168.1.232:38057.'
    }
    $phoneIp = $null
    $phonePort = [int]$Matches[2]
    if (-not [System.Net.IPAddress]::TryParse($Matches[1], [ref]$phoneIp) -or $phonePort -lt 1 -or $phonePort -gt 65535) {
        throw 'The phone IP address or port is invalid.'
    }
    Invoke-PhoneAdb -AdbArguments @('connect', $Address) | Out-Null
}

$connected = @(Get-ConnectedPhones)
if ($Address -and -not ($connected -contains $Address)) {
    # A paired phone may already be online under its mDNS serial, or its
    # connection port may have changed since the address was copied.
    $advertised = @(
        foreach ($line in (Invoke-PhoneAdb -AdbArguments @('mdns', 'services'))) {
            if ($line -match '^(\S+)\s+_adb-tls-connect\._tcp\s+(\S+)$') {
                $serviceName = $Matches[1]
                $serviceAddress = $Matches[2]
                if ($serviceAddress.StartsWith($phoneIp.ToString() + ':', [StringComparison]::Ordinal)) {
                    [pscustomobject]@{ Serial = "$serviceName._adb-tls-connect._tcp"; Address = $serviceAddress }
                }
            }
        }
    )
    if ($advertised.Count -eq 1) {
        $advertisedPhone = $advertised[0]
        if (-not ($connected -contains $advertisedPhone.Serial) -and $advertisedPhone.Address -ne $Address) {
            Invoke-PhoneAdb -AdbArguments @('connect', $advertisedPhone.Address) | Out-Null
            $connected = @(Get-ConnectedPhones)
        }
    }
}
if ($connected.Count -eq 0 -and -not $Address -and $saved.hardwareSerial) {
    # Discover only the previously selected phone, rather than connecting to
    # unrelated Android devices advertising on the same Wi-Fi network.
    $prefix = 'adb-' + $saved.hardwareSerial + '-'
    $services = @(Invoke-PhoneAdb -AdbArguments @('mdns', 'services'))
    $matchesForPhone = @(
        foreach ($line in $services) {
            if ($line -match '^(\S+)\s+_adb-tls-connect\._tcp\s+(\S+)$') {
                if ($Matches[1].StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase)) { $Matches[2] }
            }
        }
    )
    if ($matchesForPhone.Count -eq 1) {
        Invoke-PhoneAdb -AdbArguments @('connect', $matchesForPhone[0]) | Out-Null
        $connected = @(Get-ConnectedPhones)
    }
}

if ($DeviceSerial) {
    $selected = @($connected | Where-Object { $_ -eq $DeviceSerial })
} elseif ($Address) {
    $selected = @($connected | Where-Object { $_ -eq $Address })
    if ($selected.Count -eq 0 -and $advertisedPhone) {
        $selected = @($connected | Where-Object { $_ -eq $advertisedPhone.Serial })
        if ($selected.Count -eq 0) { $selected = @($connected | Where-Object { $_ -eq $advertisedPhone.Address }) }
    }
} elseif ($saved.hardwareSerial) {
    $knownPrefix = 'adb-' + $saved.hardwareSerial + '-'
    $selected = @($connected | Where-Object {
        $_ -eq $saved.hardwareSerial -or $_ -eq $saved.adbSerial -or $_.StartsWith($knownPrefix, [StringComparison]::OrdinalIgnoreCase)
    })
    if ($selected.Count -eq 0 -and $connected.Count -eq 1) { $selected = $connected }
} else {
    $selected = $connected
}

if ($selected.Count -ne 1) {
    if ($Address -and $selected.Count -eq 0) {
        throw "The phone at $Address is not connected. Keep Wireless debugging enabled on the same Wi-Fi, verify its current connection IP/port, or connect USB and allow debugging."
    }
    if ($connected.Count -eq 0) {
        throw 'No authorized phone is connected. Connect USB and allow debugging, or enable Wireless debugging and rerun with -Address IP:PORT. If authentication fails, pair the phone first (see buyer_mobile/README.md).'
    }
    throw 'Choose one connected phone with -DeviceSerial SERIAL (use adb devices -l to list them), or provide its -Address IP:PORT.'
}
$phoneSerial = $selected[0]
$hardwareSerial = (Invoke-PhoneAdb -AdbArguments @('-s', $phoneSerial, 'shell', 'getprop', 'ro.serialno') -join '').Trim()
$model = (Invoke-PhoneAdb -AdbArguments @('-s', $phoneSerial, 'shell', 'getprop', 'ro.product.model') -join '').Trim()
if (-not $hardwareSerial) { throw 'Could not verify the selected phone identity.' }
if ($saved.hardwareSerial -and $saved.hardwareSerial -ne $hardwareSerial -and -not $Address -and -not $DeviceSerial) {
    throw 'A different phone is connected. Select it explicitly with -DeviceSerial SERIAL before continuing.'
}

try {
    $health = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/categories' -UseBasicParsing -TimeoutSec 10
    $healthJson = $health.Content | ConvertFrom-Json
    if ([int]$health.StatusCode -ne 200 -or -not ($healthJson.PSObject.Properties.Name -contains 'categories')) { throw 'Unexpected backend response.' }
} catch {
    throw 'Start the shared Laravel backend in another terminal: cd C:\Invoiz; php artisan serve --host=127.0.0.1 --port=8000. Then rerun this script.'
}

Invoke-PhoneAdb -AdbArguments @('-s', $phoneSerial, 'reverse', 'tcp:8000', 'tcp:8000') | Out-Null
# Check from Android as well as Windows. Print only the HTTP status, not data.
# Keep stdin open briefly: Android netcat can otherwise exit at EOF before
# Laravel sends its response, giving a false connection failure.
$probeCommand = "{ printf 'GET /api/categories HTTP/1.1\r\nHost: 127.0.0.1:8000\r\nConnection: close\r\n\r\n'; sleep 2; } | toybox nc -q 5 -w 5 -W 8 127.0.0.1 8000"
$phoneResponse = @(Invoke-PhoneAdb -AdbArguments @('-s', $phoneSerial, 'shell', $probeCommand))
if (-not ($phoneResponse | Where-Object { $_ -match '^HTTP/1\.[01] 200\b' })) {
    throw 'The Windows backend responds, but the phone could not reach it through ADB. Reconnect USB/Wireless debugging and rerun this script.'
}

$installed = Invoke-PhoneAdb -AdbArguments @('-s', $phoneSerial, 'shell', 'pm', 'path', 'com.invoiz.invoiz')
if (-not ($installed | Where-Object { $_ -match '^package:' })) {
    throw 'The INVOIZ Android app is not installed on this phone. Build/install it with INVOIZ_API_BASE_URL=http://127.0.0.1:8000/api first.'
}
$launch = Invoke-PhoneAdb -AdbArguments @('-s', $phoneSerial, 'shell', 'am', 'start', '-W', '-n', 'com.invoiz.invoiz/.MainActivity')
if (-not ($launch | Where-Object { $_ -match '^Status: ok' })) { throw ('INVOIZ could not open: ' + ($launch -join ' ')) }

New-Item -ItemType Directory -Path $stateDirectory -Force | Out-Null
@{ hardwareSerial = $hardwareSerial; adbSerial = $phoneSerial; model = $model } |
    ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding UTF8
Write-Output "INVOIZ opened on $model. Phone-to-backend connection verified (HTTP 200)."
Write-Output 'Keep Laravel and USB/Wireless debugging connected. Rerun this script after reconnecting the phone or restarting ADB.'
