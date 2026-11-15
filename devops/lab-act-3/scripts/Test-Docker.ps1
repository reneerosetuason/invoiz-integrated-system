param(
    [string]$Image = 'invoiz-lab:local',
    [int]$Port = 8085
)

$ErrorActionPreference = 'Stop'
$dockerCommand = Get-Command docker -ErrorAction SilentlyContinue
if (-not $dockerCommand) {
    $dockerDirectory = Join-Path $env:LOCALAPPDATA 'Programs\DockerDesktop\resources\bin'
    if (-not (Test-Path -LiteralPath (Join-Path $dockerDirectory 'docker.exe'))) {
        throw 'Docker CLI not found. Open a new terminal after installing Docker Desktop.'
    }
    # Process-local PATH only; no system settings are changed.
    $env:PATH = "$dockerDirectory;$env:PATH"
}

$listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $Port)
try { $listener.Start() } finally { $listener.Stop() }
$containerId = $null
$previousKey = $env:APP_KEY
$keyBytes = New-Object byte[] 32
$random = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$random.GetBytes($keyBytes)
$random.Dispose()
$env:APP_KEY = 'base64:' + [Convert]::ToBase64String($keyBytes)

try {
    $arguments = @(
        'run', '-d', '--name', 'invoiz-lab-verification',
        '--memory=256m', '--cpus=1', '--pids-limit=128',
        '-p', "127.0.0.1:${Port}:80",
        '--env', 'APP_KEY',
        '-e', 'APP_ENV=production', '-e', 'APP_DEBUG=false',
        '-e', "APP_URL=http://127.0.0.1:$Port",
        '-e', 'DB_CONNECTION=sqlite', '-e', 'DB_DATABASE=:memory:',
        '-e', 'DB_URL=', '-e', 'DB_HOST=invalid.example', '-e', 'DB_PORT=0',
        '-e', 'DB_USERNAME=', '-e', 'DB_PASSWORD=',
        '-e', 'SESSION_DRIVER=file', '-e', 'CACHE_STORE=file',
        '-e', 'SESSION_COOKIE=invoiz_lab_verification',
        '-e', 'QUEUE_CONNECTION=sync', '-e', 'MAIL_MAILER=log', $Image
    )
    $created = & docker @arguments
    if ($LASTEXITCODE -ne 0) { throw 'Container creation failed; no existing container will be removed.' }
    $containerId = ($created | Select-Object -Last 1).Trim()
    if ($containerId -notmatch '^[a-f0-9]{64}$') { throw 'Unexpected Docker container identifier.' }
    Write-Output "Started isolated container $($containerId.Substring(0,12)) on 127.0.0.1:$Port (256 MiB, one CPU)."

    $response = $null
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        try {
            $response = Invoke-WebRequest "http://127.0.0.1:$Port/up" -UseBasicParsing -TimeoutSec 2
            if ($response.StatusCode -eq 200) { break }
        } catch { Start-Sleep -Seconds 2 }
    }
    if (-not $response -or $response.StatusCode -ne 200) { throw 'Container /up did not return HTTP 200.' }
    Write-Output "PASS: Host HTTP GET /up returned $($response.StatusCode)."

    $verificationPhp = @'
<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('Database isolation check failed.');
}
if (Illuminate\Support\Facades\DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
    throw new RuntimeException('Unexpected PDO connection.');
}
foreach (['pdo_mysql', 'pdo_sqlite', 'mbstring', 'bcmath', 'gd', 'zip', 'dom', 'curl', 'fileinfo'] as $extension) {
    if (!extension_loaded($extension)) { throw new RuntimeException('Missing extension: '.$extension); }
}
foreach (['.env', 'auth.json', '.git', 'public/downloads', 'public/buyer-app', 'devops/lab-act-3/.runtime'] as $path) {
    if (file_exists('/var/www/html/'.$path)) { throw new RuntimeException('Unexpected host content: '.$path); }
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/var/www/html/database', FilesystemIterator::SKIP_DOTS)) as $file) {
    if (preg_match('/\.(sql|sqlite|sqlite3)$/i', $file->getFilename())) { throw new RuntimeException('Unexpected database dump.'); }
}
if (!file_exists('/var/www/html/public/build/manifest.json')) { throw new RuntimeException('Missing Vite build.'); }
echo 'PASS: PHP '.PHP_VERSION.'; required extensions, SQLite :memory: PDO connection, Vite manifest, host-content exclusions.'.PHP_EOL;
echo trim(file_get_contents('/usr/local/share/invoiz-lab-version.txt')).PHP_EOL;
'@
    $verificationPhp | & docker exec -i $containerId php
    if ($LASTEXITCODE -ne 0) { throw 'Container runtime verification failed.' }
    $mounts = & docker inspect --format '{{json .Mounts}}' $containerId
    if ($LASTEXITCODE -ne 0 -or $mounts.Trim() -ne '[]') { throw 'Unexpected container mounts.' }
    Write-Output 'PASS: No host bind mounts or database volumes.'
    $health = ''
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        $health = & docker inspect --format '{{.State.Health.Status}}' $containerId
        if ($health -eq 'healthy') { break }
        Start-Sleep -Seconds 2
    }
    if ($health -ne 'healthy') { throw "Docker health status: $health" }
    Write-Output 'PASS: Docker HEALTHCHECK reports healthy.'
    & docker image inspect --format 'Verified local image: {{.Id}}; size={{.Size}} bytes' $Image
    if ($LASTEXITCODE -ne 0) { throw 'Image inspection failed.' }
} finally {
    $env:APP_KEY = $previousKey
    if ($containerId -match '^[a-f0-9]{64}$') {
        & docker stop --time 10 $containerId | Out-Null
        & docker rm $containerId | Out-Null
        Write-Output 'Stopped and removed only the lab verification container.'
    }
}
