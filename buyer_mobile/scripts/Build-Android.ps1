param([string]$ApiBaseUrl = 'https://invoiz.store/api', [switch]$NoPub)

$ErrorActionPreference = 'Stop'
$buyerRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$buildArgs = @('build', 'apk', '--debug')
if ($NoPub) { $buildArgs += '--no-pub' }
if ($ApiBaseUrl) {
    $apiUri = [Uri]$ApiBaseUrl
    if (-not $apiUri.IsAbsoluteUri -or $apiUri.Scheme -notin @('http', 'https') -or $apiUri.UserInfo -or $apiUri.Query -or $apiUri.Fragment -or $apiUri.AbsolutePath.TrimEnd('/') -ne '/api') {
        throw 'ApiBaseUrl must be the shared website HTTP(S) address ending in /api.'
    }
    $buildArgs += "--dart-define=INVOIZ_API_BASE_URL=$($ApiBaseUrl.TrimEnd('/'))"
}
Push-Location -LiteralPath $buyerRoot
try {
    # Flutter writes informational diagnostics to stderr too. Check its exit code.
    $previousErrorAction = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        & flutter @buildArgs 2>&1 | ForEach-Object { $_.ToString() }
        $flutterExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorAction
    }
    if ($flutterExitCode -ne 0) { throw 'Flutter Android build failed.' }
    Write-Output (Join-Path $buyerRoot 'build\app\outputs\flutter-apk\app-debug.apk')
} finally {
    Pop-Location
}
