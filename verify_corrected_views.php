<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

config(['session.driver' => 'array']);
$app->forgetInstance('session');
$app->forgetInstance('session.store');

$delivery = Delivery::with(['pickupRequest', 'order'])->findOrFail(20);
$order = Order::findOrFail($delivery->order_id);
$center = LogisticsCenter::find($delivery->center_id);

$staffCandidates = User::where('center_id', $delivery->center_id)
    ->whereIn('role', ['staff', 'logistics_staff', 'logistics_admin', 'admin'])
    ->whereNotNull('email_verified_at')
    ->orderByRaw("FIELD(role, 'staff', 'logistics_staff', 'logistics_admin', 'admin')")
    ->get(['id', 'name', 'email', 'role', 'center_id', 'logistics_company_id', 'is_admin']);

$target = 'TRK-20261102-2KLN';
$kernel = $app->make(Kernel::class);

function getPage(Kernel $kernel, string $uri, ?int $userId, array $sessionData = []): array
{
    Auth::logout();
    session()->flush();
    if ($userId !== null) {
        Auth::onceUsingId($userId);
    }
    foreach ($sessionData as $key => $value) {
        session([$key => $value]);
    }

    $request = Request::create($uri, 'GET');
    $request->headers->set('Accept', 'text/html');
    $response = $kernel->handle($request);
    $html = $response->getStatusCode() === 200 ? $response->getContent() : '';

    Auth::logout();
    session()->flush();

    return ['status' => $response->getStatusCode(), 'html' => $html];
}

function text(string $html): string
{
    $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return preg_replace('/\s+/', ' ', $plain) ?? '';
}

function sliceAround(string $html, string $needle, int $before = 4000, int $after = 6000): array
{
    $pos = strpos($html, $needle);
    if ($pos === false) {
        return ['found' => false, 'slice' => ''];
    }
    return ['found' => true, 'slice' => substr($html, max(0, $pos - $before), $before + $after)];
}

echo "Delivery 20: order {$delivery->order_id}, buyer {$order->buyer_id}, seller {$delivery->seller_id}, center {$delivery->center_id} ({$center?->name})\n";
echo "Staff candidates for center {$delivery->center_id}:\n";
foreach ($staffCandidates as $u) {
    echo "  id={$u->id} role={$u->role} is_admin=" . (int) $u->is_admin . " company={$u->logistics_company_id} {$u->email}\n";
}
echo str_repeat('-', 80) . "\n";

$results = [];

// Buyer view
$buyer = getPage($kernel, '/orders/' . $order->id, null, ['buyer' => ['id' => $order->buyer_id]]);
$buyerText = text($buyer['html']);
$results['buyer'] = [
    'status' => $buyer['status'],
    'label_present' => str_contains($buyerText, 'Pickup Approved — Awaiting Rider'),
    'pickup_card' => str_contains($buyerText, 'Pickup Status'),
    'not_collected' => str_contains($buyerText, 'has not been collected by a rider yet'),
    'no_at_hub' => ! str_contains($buyerText, 'AT HUB'),
    'no_sorted_hub' => ! str_contains($buyerText, 'Sorted at Logistics Hub'),
    'no_received_hub' => ! str_contains($buyerText, 'Received at Logistics Hub'),
];

// Seller view
$seller = getPage($kernel, '/seller/orders/' . $order->id, (int) $delivery->seller_id, ['active_role' => 'seller']);
$sellerText = text($seller['html']);
$results['seller'] = [
    'status' => $seller['status'],
    'tracking_present' => str_contains($seller['html'], $target),
    'label_present' => str_contains($sellerText, 'Pickup Approved — Awaiting Rider'),
    'order_label' => str_contains($sellerText, 'Ready for Pickup'),
    'not_collected_note' => str_contains($sellerText, 'The parcel has not been collected yet.'),
    'no_sorted_hub' => ! str_contains($sellerText, 'Sorted at Logistics Hub'),
    'no_received_hub' => ! str_contains($sellerText, 'Received at Logistics Hub'),
];

// Logistics view (all tab)
$staff = $staffCandidates->first();
$logistics = $staff
    ? getPage($kernel, '/logistics/sorting-center', (int) $staff->id)
    : ['status' => null, 'html' => ''];
$row = sliceAround($logistics['html'], $target);
$rowStart = strrpos(substr($logistics['html'], 0, strpos($logistics['html'], $target) ?: 0), '<tr');
$rowEnd = strpos($logistics['html'], '</tr>', strpos($logistics['html'], $target) ?: 0);
$rowHtml = ($rowStart !== false && $rowEnd !== false) ? substr($logistics['html'], $rowStart, $rowEnd - $rowStart) : '';
$rowText = text($rowHtml);
$results['logistics'] = [
    'staff_id' => $staff?->id,
    'status' => $logistics['status'],
    'row_present' => $row['found'],
    'row_has_pending_arrival' => str_contains(strtolower($rowText), 'pending arrival'),
    'awaiting_pickup' => str_contains($rowText, 'Awaiting pickup'),
    'receipt_unverified' => str_contains($rowText, 'Receipt unverified'),
    'no_receive_form' => ! str_contains($rowHtml, 'name="center_id"'),
    'no_scan_action' => ! preg_match('/action="[^"]*\/scan"/', $rowHtml),
    'no_sort_action' => ! str_contains($rowHtml, 'Confirm sort'),
    'no_sorted_badge' => ! str_contains($rowText, 'Sorted'),
];

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
