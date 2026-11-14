<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\HubTransportDriver;
use App\Models\Rider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;

config(['session.driver' => 'array']);
$app->forgetInstance('session');
$app->forgetInstance('session.store');

$kernel = $app->make(Kernel::class);

function apiGet(Kernel $kernel, string $uri): array
{
    $request = Request::create($uri, 'GET');
    $request->headers->set('Accept', 'application/json');
    $response = $kernel->handle($request);

    return [
        'status' => $response->getStatusCode(),
        'body' => json_decode($response->getContent(), true),
    ];
}

$rider = Rider::with('user')
    ->whereNotNull('approved_at')
    ->where('status', '!=', 'inactive')
    ->whereHas('user', fn ($q) => $q->where('role', 'rider')->whereNotNull('email_verified_at'))
    ->first();

$driver = HubTransportDriver::with('company')
    ->where('status', '!=', 'inactive')
    ->whereHas('company', fn ($q) => $q->where('status', 'active'))
    ->first();

$out = ['delivery_rider' => null, 'hub_transport_driver' => null];

if ($rider) {
    Sanctum::actingAs($rider->user, ['rider']);
    $profile = apiGet($kernel, '/api/logistics/rider/profile');
    $dashboard = apiGet($kernel, '/api/logistics/rider/dashboard');
    $deliveries = apiGet($kernel, '/api/logistics/rider/deliveries');
    $earnings = apiGet($kernel, '/api/logistics/rider/earnings');
    $out['delivery_rider'] = [
        'rider_id' => $rider->id,
        'user_id' => $rider->user_id,
        'approved_at' => $rider->approved_at?->toDateTimeString(),
        'profile_status' => $profile['status'],
        'profile_name' => $profile['body']['rider']['name'] ?? null,
        'dashboard_status' => $dashboard['status'],
        'deliveries_status' => $deliveries['status'],
        'deliveries_count' => is_array($deliveries['body']['deliveries'] ?? null) ? count($deliveries['body']['deliveries']) : null,
        'earnings_status' => $earnings['status'],
        'also_has_hub_driver_profile' => HubTransportDriver::where('user_id', $rider->user_id)->exists(),
    ];
} else {
    $out['delivery_rider'] = ['error' => 'No approved active rider account found.'];
}

if ($driver) {
    Sanctum::actingAs($driver, ['hub-courier']);
    $profile = apiGet($kernel, '/api/logistics/courier/profile');
    $dashboard = apiGet($kernel, '/api/logistics/courier/dashboard');
    $transfers = apiGet($kernel, '/api/logistics/courier/transfers');
    $earnings = apiGet($kernel, '/api/logistics/courier/earnings');
    $earningRows = \App\Models\HubTransferEarning::where('hub_transport_driver_id', $driver->id)->get();
    $duplicateGroups = $earningRows
        ->groupBy(fn ($row) => $row->hub_transfer_id.'|'.$row->recipient_role)
        ->filter(fn ($group) => $group->count() > 1)
        ->map(fn ($group) => $group->pluck('id')->values()->all());
    $out['hub_transport_driver'] = [
        'driver_id' => $driver->id,
        'user_id' => $driver->user_id,
        'company_id' => $driver->logistics_company_id,
        'profile_status' => $profile['status'],
        'profile_name' => $profile['body']['courier']['name'] ?? null,
        'dashboard_status' => $dashboard['status'],
        'transfers_status' => $transfers['status'],
        'transfers_count' => is_array($transfers['body']['transfers'] ?? null) ? count($transfers['body']['transfers']) : null,
        'earnings_status' => $earnings['status'],
        'earnings_summary' => $earnings['body']['summary'] ?? null,
        'earnings_rows_returned' => is_array($earnings['body']['earnings'] ?? null) ? count($earnings['body']['earnings']) : null,
        'earnings_total_rows' => $earningRows->count(),
        'earnings_duplicate_groups' => $duplicateGroups->all(),
        'also_has_delivery_rider_profile' => Rider::where('user_id', $driver->user_id)->exists(),
    ];
} else {
    $out['hub_transport_driver'] = ['error' => 'No active hub transport driver found.'];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
