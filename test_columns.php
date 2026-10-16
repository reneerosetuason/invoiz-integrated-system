<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

$s = \App\Models\Seller::whereNotNull('id_image')->orWhereNotNull('dti_certificate')->latest()->first();
if (! $s) {
    echo "No seller with documents found." . PHP_EOL;
    exit;
}

echo "Testing seller #{$s->id} ({$s->display_name}):" . PHP_EOL;

$admin = \App\Models\User::where('role', 'admin')->orWhere('is_admin', true)->first();
\Illuminate\Support\Facades\Auth::login($admin);

$session = $app->make('session')->driver();
$session->start();

// Test admin document routes
foreach (['id', 'dti', 'bir'] as $type) {
    $req = \Illuminate\Http\Request::create("/admin/sellers/{$s->id}/document/{$type}", 'GET');
    $req->setLaravelSession($session);
    $res = $kernel->handle($req);
    echo "Document route [$type] status: " . $res->getStatusCode() . ' | Content-Type: ' . $res->headers->get('Content-Type') . PHP_EOL;
}

// Test /storage/ paths
foreach ([$s->id_image, $s->dti_certificate, $s->bir_2303] as $f) {
    if (! $f) continue;
    $req = \Illuminate\Http\Request::create("/storage/{$f}", 'GET');
    $req->setLaravelSession($session);
    $res = $kernel->handle($req);
    echo "Storage route [/storage/{$f}] status: " . $res->getStatusCode() . ' | Content-Type: ' . $res->headers->get('Content-Type') . PHP_EOL;
}