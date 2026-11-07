param(
    [ValidateSet('buyer', 'rider')]
    [string[]]$AppKind = @('buyer', 'rider'),
    [string]$AndroidSdk = "$env:LOCALAPPDATA\Android\Sdk",
    [string]$JavaCommand = 'C:\Program Files\Android\Android Studio\jbr\bin\java.exe'
)

$ErrorActionPreference = 'Stop'
$workspaceRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$buildTools = Get-ChildItem -LiteralPath (Join-Path $AndroidSdk 'build-tools') -Directory |
    Where-Object { Test-Path -LiteralPath (Join-Path $_.FullName 'aapt.exe') } |
    Sort-Object { [version]$_.Name } -Descending | Select-Object -First 1
if (-not $buildTools) { throw 'Android build tools are required to verify the APKs.' }
$aaptCommand = Join-Path $buildTools.FullName 'aapt.exe'
$signerJar = Join-Path $buildTools.FullName 'lib\apksigner.jar'

function Get-ApkCertificate([string]$ApkPath) {
    $signerOutput = & $JavaCommand -jar $signerJar verify --print-certs $ApkPath
    if ($LASTEXITCODE -ne 0) { throw "APK signature verification failed: $ApkPath" }
    $certificateMatch = [regex]::Match(($signerOutput -join "`n"), 'Signer #1 certificate SHA-256 digest: ([a-fA-F0-9]{64})')
    if (-not $certificateMatch.Success) { throw "Missing signing certificate: $ApkPath" }
    return $certificateMatch.Groups[1].Value.ToLowerInvariant()
}

$apps = @(
    @{key='buyer'; folder='buyer_mobile'; package='com.invoiz.invoiz'},
    @{key='rider'; folder='rider_mobile'; package='com.invoize.invoize_rider'}
) | Where-Object { $AppKind -contains $_.key }
$manifestPath = Join-Path $workspaceRoot 'public\downloads\manifest.json'
$manifest = [ordered]@{}
if (Test-Path -LiteralPath $manifestPath) {
    $previousManifest = Get-Content -LiteralPath $manifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
    foreach ($property in $previousManifest.PSObject.Properties) {
        $manifest[$property.Name] = $property.Value
    }
}
$validatedApps = @()
foreach ($app in $apps) {
    $appRoot = Join-Path $workspaceRoot $app.folder
    $versionMatch = [regex]::Match((Get-Content -LiteralPath (Join-Path $appRoot 'pubspec.yaml') -Raw), '(?m)^version:\s*(\d+\.\d+\.\d+)\+(\d+)\s*$')
    if (-not $versionMatch.Success) { throw "Invalid version in $($app.folder)/pubspec.yaml" }
    $version = $versionMatch.Groups[1].Value
    $build = [int]$versionMatch.Groups[2].Value
    $source = Join-Path $appRoot 'build\app\outputs\flutter-apk\app-release.apk'
    $destination = Join-Path $workspaceRoot "public\downloads\invoiz-$($app.key).apk"
    $badging = (& $aaptCommand dump badging $source) -join "`n"
    if ($LASTEXITCODE -ne 0 -or $badging -notmatch "package: name='$([regex]::Escape($app.package))' versionCode='$build' versionName='$([regex]::Escape($version))'") {
        throw "APK package/version does not match $($app.folder)/pubspec.yaml. Build the release APK first."
    }
    $certificate = Get-ApkCertificate $source
    if (Test-Path -LiteralPath $destination) {
        if ((Get-ApkCertificate $destination) -ne $certificate) { throw "Signing key changed for $($app.key); existing phones could not update in place." }
        $oldBadging = (& $aaptCommand dump badging $destination) -join "`n"
        $oldBuild = [int]([regex]::Match($oldBadging, "versionCode='(\d+)'").Groups[1].Value)
        if ($oldBuild -ge $build) { throw "Increase the $($app.key) build number before publishing." }
    }
    $hash = (Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash.ToLowerInvariant()
    $manifest[$app.key] = [ordered]@{version=$version; build=$build; bytes=(Get-Item -LiteralPath $source).Length; sha256=$hash; package=$app.package}
    $validatedApps += @{key=$app.key; source=$source; destination=$destination; hash=$hash}
}

# Verify selected builds before replacing downloads. Keep previous installers private.
$backupDirectory = Join-Path $workspaceRoot ('storage\app\backups\mobile-release-' + [DateTime]::UtcNow.ToString('yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Path $backupDirectory -Force | Out-Null
foreach ($app in $validatedApps) {
    $staged = Join-Path $backupDirectory "new-$($app.key).apk"
    Copy-Item -LiteralPath $app.source -Destination $staged
    if ((Get-FileHash -LiteralPath $staged -Algorithm SHA256).Hash.ToLowerInvariant() -ne $app.hash) { throw 'Staged APK checksum mismatch.' }
    if (Test-Path -LiteralPath $app.destination) {
        [IO.File]::Replace($staged, $app.destination, (Join-Path $backupDirectory "previous-$($app.key).apk"))
    } else {
        Move-Item -LiteralPath $staged -Destination $app.destination
    }
}
$stagedManifest = Join-Path $backupDirectory 'new-manifest.json'
[IO.File]::WriteAllText($stagedManifest, ($manifest | ConvertTo-Json -Depth 4) + "`n", [Text.UTF8Encoding]::new($false))
if (Test-Path -LiteralPath $manifestPath) {
    [IO.File]::Replace($stagedManifest, $manifestPath, (Join-Path $backupDirectory 'previous-manifest.json'))
} else {
    Move-Item -LiteralPath $stagedManifest -Destination $manifestPath
}
$manifest | ConvertTo-Json -Depth 4
