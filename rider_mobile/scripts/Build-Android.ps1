param(
    [string]$ApiBaseUrl = 'http://127.0.0.1:8000/api/logistics',
    [string]$FlutterCommand = '',
    [switch]$NoPub
)

$ErrorActionPreference = 'Stop'
$riderRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if (-not $FlutterCommand) {
    $workspaceSdk = Join-Path $riderRoot '..\.tools\flutter-rider-3.44.5\bin\flutter.bat'
    $FlutterCommand = if (Test-Path -LiteralPath $workspaceSdk -PathType Leaf) { $workspaceSdk } else { 'flutter' }
}
$apiUri = [Uri]$ApiBaseUrl
if (-not $apiUri.IsAbsoluteUri -or $apiUri.Scheme -notin @('http', 'https') -or $apiUri.UserInfo -or $apiUri.Query -or $apiUri.Fragment -or $apiUri.AbsolutePath.TrimEnd('/') -ne '/api/logistics') {
    throw 'ApiBaseUrl must be the shared Laravel HTTP(S) address ending in /api/logistics.'
}

Push-Location -LiteralPath $riderRoot
try {
    if (-not (Test-Path -LiteralPath '.env')) {
        Copy-Item -LiteralPath '.env.example' -Destination '.env'
    }
    $buildArgs = @('build', 'apk', '--debug', "--dart-define=API_BASE_URL=$($ApiBaseUrl.TrimEnd('/'))")
    if ($NoPub) { $buildArgs += '--no-pub' }
    $previousErrorAction = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        & $FlutterCommand @buildArgs 2>&1 | ForEach-Object { $_.ToString() }
        $buildExit = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorAction
    }
    if ($buildExit -ne 0) { throw 'Rider Android build failed.' }
    Write-Output (Join-Path $riderRoot 'build\app\outputs\flutter-apk\app-debug.apk')
} finally {
    Pop-Location
}
