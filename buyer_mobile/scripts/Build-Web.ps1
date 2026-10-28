param([string]$ApiBaseUrl = '', [switch]$NoPub)

$ErrorActionPreference = 'Stop'
$buyerRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $buyerRoot '..'))
if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'artisan') -PathType Leaf)) {
    throw 'Run this script from the buyer_mobile app inside the shared Laravel project.'
}
$outputPath = Join-Path $projectRoot 'public\buyer-app'
$buildArgs = @('build', 'web', '--base-href=/buyer-app/')
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
    & flutter @buildArgs
    if ($LASTEXITCODE -ne 0) { throw 'Flutter web build failed.' }
    New-Item -ItemType Directory -Path $outputPath -Force | Out-Null
    Get-ChildItem -LiteralPath (Join-Path $buyerRoot 'build\web') -Force | Copy-Item -Destination $outputPath -Recurse -Force
    Write-Output 'Buyer app is available at /buyer-app/ on the shared Laravel server.'
} finally {
    Pop-Location
}
