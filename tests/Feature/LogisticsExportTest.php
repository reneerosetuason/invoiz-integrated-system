<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\DeliveryProof;
use App\Models\LogisticsCenter;
use App\Models\Order;
use App\Models\Remittance;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class LogisticsExportTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role, ?LogisticsCenter $center = null): User
    {
        return User::create([
            'name' => ucfirst(str_replace('_', ' ', $role)) . ' ' . Str::random(5),
            'email' => 'export-' . Str::random(12) . '@test.local',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'is_admin' => $role === 'admin',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'center_id' => $center?->id,
        ]);
    }

    private function center(string $name): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name,
            'address' => $name . ' Address',
            'city' => 'Test City',
            'province' => 'Test Province',
            'phone' => '09170000000',
            'is_active' => true,
        ]);
    }

    private function rider(LogisticsCenter $center): Rider
    {
        return Rider::create([
            'center_id' => $center->id,
            'name' => 'Export Rider ' . Str::random(5),
            'email' => 'rider-' . Str::random(12) . '@test.local',
            'phone' => '09173333333',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
        ]);
    }

    private function delivery(LogisticsCenter $center, array $overrides = []): Delivery
    {
        return Delivery::create(array_merge([
            'tracking_number' => 'EXP-' . strtoupper(Str::random(12)),
            'center_id' => $center->id,
            'destination_center_id' => $center->id,
            'status' => 'assigned',
            'parcel_status' => 'scanned',
            'sender_name' => 'Export Sender',
            'recipient_name' => 'Export Recipient',
            'sender_phone' => '09171111111',
            'recipient_phone' => '09172222222',
            'sender_address' => 'Private sender address',
            'recipient_address' => 'Private recipient address',
            'notes' => 'private internal note',
            'priority' => 'normal',
            'payment_method' => 'cash_on_delivery',
            'amount_to_collect' => 170,
            'delivery_fee' => 50,
        ], $overrides));
    }

    private function csv(User $user, string $uri): array
    {
        $response = $this->actingAs($user)->get($uri);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();

        return [$csv, $this->csvRows($csv)];
    }

    private function csvRows(string $csv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv)) ?: [];

        return array_values(array_filter(array_map(
            fn (string $line) => str_getcsv(ltrim($line, "\xEF\xBB\xBF"), ',', '"', ''),
            $lines
        ), fn (array $row) => $row !== ['']));
    }

    private function row(array $rows, string $column, string $value): ?array
    {
        $headers = $rows[0] ?? [];
        $index = array_search($column, $headers, true);
        if ($index === false) {
            return null;
        }

        foreach (array_slice($rows, 1) as $row) {
            if (($row[$index] ?? null) === $value) {
                return $row;
            }
        }

        return null;
    }

    public function test_admin_delivery_export_contains_all_centers_and_audits_without_pii(): void
    {
        $admin = $this->user('admin');
        $centerA = $this->center('Export Center A');
        $centerB = $this->center('Export Center B');
        $deliveryA = $this->delivery($centerA, ['recipient_name' => 'Private Person A']);
        $deliveryB = $this->delivery($centerB, ['recipient_name' => 'Private Person B']);

        [$csv, $rows] = $this->csv($admin, '/logistics/deliveries/export?search=' . $deliveryA->tracking_number);

        $this->assertCount(2, $rows);
        $this->assertSame($deliveryA->tracking_number, $rows[1][0]);
        $this->assertStringNotContainsString($deliveryB->tracking_number, $csv);
        $this->assertStringNotContainsString('09171111111', $csv);
        $this->assertStringNotContainsString('Private sender address', $csv);
        $this->assertStringNotContainsString('private internal note', $csv);

        $audit = AuditLog::where('action', 'report.exported')
            ->where('entity', 'report')
            ->where('meta->report', 'logistics.deliveries')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame('csv', $audit->meta['format']);
        $this->assertSame(1, $audit->meta['row_count']);
        $this->assertTrue($audit->meta['filters']['search_applied']);
        $this->assertStringNotContainsString($deliveryA->tracking_number, (string) json_encode($audit->meta));
    }

    public function test_staff_delivery_export_stays_in_their_center_despite_center_parameter(): void
    {
        $centerA = $this->center('Scoped Center A');
        $centerB = $this->center('Scoped Center B');
        $staff = $this->user('logistics_staff', $centerA);
        $deliveryA = $this->delivery($centerA, ['status' => 'delivered']);
        $deliveryB = $this->delivery($centerB, ['status' => 'delivered']);

        [$csv, $rows] = $this->csv(
            $staff,
            '/logistics/deliveries/export?center_id=' . $centerB->id . '&status=delivered'
        );

        $this->assertCount(2, $rows);
        $this->assertSame($deliveryA->tracking_number, $rows[1][0]);
        $this->assertStringNotContainsString($deliveryB->tracking_number, $csv);
    }

    public function test_delivery_export_preserves_filters_and_neutralizes_formulas(): void
    {
        $admin = $this->user('admin');
        $center = $this->center('Formula Center');
        $formula = $this->delivery($center, [
            'sender_name' => ' =HYPERLINK("http://evil.test","click")',
            'recipient_name' => 'Recipient, "Quoted"',
            'status' => 'delivered',
            'amount_to_collect' => 250,
            'cod_amount_received' => 250,
        ]);
        $other = $this->delivery($center, [
            'status' => 'assigned',
            'amount_to_collect' => 170,
        ]);

        [$csv, $rows] = $this->csv($admin, '/logistics/deliveries/export?status=delivered');
        $row = $this->row($rows, 'Tracking Number', $formula->tracking_number);

        $this->assertNotNull($row);
        $this->assertSame("' =HYPERLINK(\"http://evil.test\",\"click\")", $row[5]);
        $this->assertSame('Recipient, "Quoted"', $row[6]);
        $this->assertSame('250.00', number_format((float) $row[13], 2, '.', ''));
        $this->assertStringNotContainsString($other->tracking_number, $csv);
    }

    public function test_delivery_export_excludes_otp_proof_and_private_fields(): void
    {
        $admin = $this->user('admin');
        $center = $this->center('Sensitive Center');
        $delivery = $this->delivery($center);
        $delivery->delivery_otp = '654321';
        $delivery->delivery_otp_expires_at = now()->addMinutes(10);
        $delivery->save();

        DeliveryProof::create([
            'delivery_id' => $delivery->id,
            'rider_id' => $this->rider($center)->id,
            'type' => 'photo',
            'file_path' => 'delivery-proofs/private-proof.jpg',
            'signature_name' => 'Private Signature',
            'otp' => '777777',
        ]);

        [$csv] = $this->csv($admin, '/logistics/deliveries/export');

        $this->assertStringNotContainsString('654321', $csv);
        $this->assertStringNotContainsString('777777', $csv);
        $this->assertStringNotContainsString('delivery-proofs/private-proof.jpg', $csv);
        $this->assertStringNotContainsString('Private Signature', $csv);
        $this->assertStringNotContainsString('Delivery OTP', $csv);
    }

    public function test_sorting_center_export_preserves_tab_and_center_scope(): void
    {
        $centerA = $this->center('Sorting Center A');
        $centerB = $this->center('Sorting Center B');
        $staff = $this->user('logistics_staff', $centerA);
        $sortedA = $this->delivery($centerA, ['parcel_status' => 'sorted']);
        $receivedA = $this->delivery($centerA, ['parcel_status' => 'received']);
        $sortedB = $this->delivery($centerB, ['parcel_status' => 'sorted']);

        [$csv, $rows] = $this->csv($staff, '/logistics/sorting-center/export?tab=sorted');

        $this->assertCount(2, $rows);
        $this->assertSame($sortedA->tracking_number, $rows[1][0]);
        $this->assertStringNotContainsString($receivedA->tracking_number, $csv);
        $this->assertStringNotContainsString($sortedB->tracking_number, $csv);
    }

    public function test_reports_csv_uses_real_rows_and_center_scope(): void
    {
        $centerA = $this->center('Report Center A');
        $centerB = $this->center('Report Center B');
        $admin = $this->user('admin');
        $staff = $this->user('logistics_staff', $centerA);
        $this->delivery($centerA, ['status' => 'delivered']);
        $this->delivery($centerB, ['status' => 'assigned']);
        $this->delivery($centerB, ['status' => 'assigned']);

        [$adminCsv, $adminRows] = $this->csv($admin, '/logistics/reports/export/csv?tab=center');
        $this->assertNotNull($this->row($adminRows, 'Center', 'Report Center A'));
        $this->assertNotNull($this->row($adminRows, 'Center', 'Report Center B'));

        [$staffCsv, $staffRows] = $this->csv($staff, '/logistics/reports/export/csv?tab=center');
        $this->assertNotNull($this->row($staffRows, 'Center', 'Report Center A'));
        $this->assertStringNotContainsString('Report Center B', $staffCsv);

        [$deliveryCsv, $deliveryRows] = $this->csv($admin, '/logistics/reports/export/csv?tab=delivery');
        $this->assertStringNotContainsString('Array', $deliveryCsv);
        $this->assertSame('Metric', $deliveryRows[0][0]);
        $this->assertNotNull($this->row($deliveryRows, 'Metric', 'Total Deliveries'));

        [$financialCsv, $financialRows] = $this->csv($admin, '/logistics/reports/export/csv?tab=financial');
        $this->assertStringNotContainsString('Array', $financialCsv);
        $this->assertNotNull($this->row($financialRows, 'Metric', 'Transactions'));

        [$allCsv] = $this->csv($admin, '/logistics/reports/export/csv?tab=all');
        $this->assertStringNotContainsString('Array', $allCsv);
        $this->assertStringContainsString('Delivery Report', $allCsv);
        $this->assertStringContainsString('Financial Report', $allCsv);
    }

    public function test_non_logistics_users_cannot_download_exports(): void
    {
        $buyer = $this->user('buyer');

        foreach ([
            '/logistics/deliveries/export',
            '/logistics/sorting-center/export',
            '/logistics/remittances/export',
            '/logistics/reports/export/csv',
        ] as $uri) {
            $this->actingAs($buyer)
                ->get($uri)
                ->assertRedirect('/logistics/login')
                ->assertSessionHas('error');
        }
    }

    public function test_centerless_staff_exports_no_report_rows(): void
    {
        $staff = $this->user('logistics_staff');
        $center = $this->center('Unavailable Center');
        $delivery = $this->delivery($center, ['parcel_status' => 'sorted']);

        foreach ([
            '/logistics/deliveries/export',
            '/logistics/sorting-center/export',
            '/logistics/remittances/export',
            '/logistics/reports/export/csv?tab=center',
            '/logistics/reports/export/csv?tab=all',
        ] as $uri) {
            [$csv, $rows] = $this->csv($staff, $uri);

            if (! str_contains($uri, 'tab=all')) {
                $this->assertCount(1, $rows, $uri . ' should only return headers for centerless staff');
            }
            $this->assertStringNotContainsString($delivery->tracking_number, $csv, $uri);
            $this->assertStringNotContainsString('Unavailable Center', $csv, $uri);
        }
    }

    public function test_cod_remittance_export_is_read_only_and_uses_persisted_values(): void
    {
        $admin = $this->user('admin');
        $center = $this->center('COD Export Center');
        $buyer = $this->user('buyer');
        $seller = Seller::factory()->create();

        $order = Order::create([
            'order_number' => 'COD-EXPORT-' . strtoupper(Str::random(8)),
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'status' => 'delivered',
            'payment_status' => 'pending',
            'total' => 170,
            'total_amount' => 170,
            'sub_total' => 120,
            'shipping_fee' => 50,
        ]);

        $delivery = $this->delivery($center, [
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'status' => 'delivered',
            'delivered_at' => now(),
            'amount_to_collect' => 170,
            'cod_amount_received' => 170,
            'cod_received_at' => now(),
        ]);

        Transaction::create([
            'delivery_id' => $delivery->id,
            'tracking_number' => $delivery->tracking_number,
            'logistics_center_id' => $center->id,
            'amount' => 50,
            'rider_fee' => 50,
            'admin_commission' => 0,
            'status' => 'completed',
        ]);

        $remittance = Remittance::create([
            'order_id' => $order->id,
            'delivery_id' => $delivery->id,
            'seller_id' => $seller->id,
            'amount' => 120,
            'status' => 'pending',
        ]);

        $before = Remittance::count();
        [$csv, $rows] = $this->csv($admin, '/logistics/remittances/export?status=pending');
        $row = $this->row($rows, 'Order Number', $order->order_number);

        $this->assertNotNull($row);
        $this->assertSame($delivery->tracking_number, $row[1]);
        $this->assertSame('170.00', number_format((float) $row[6], 2, '.', ''));
        $this->assertSame('170.00', number_format((float) $row[7], 2, '.', ''));
        $this->assertSame('50.00', number_format((float) $row[8], 2, '.', ''));
        $this->assertSame('120.00', number_format((float) $row[9], 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $row[10], 2, '.', ''));
        $this->assertSame('balanced', $row[11]);
        $this->assertSame($before, Remittance::count());
        $this->assertSame('pending', $remittance->fresh()->status);
        $this->assertStringNotContainsString('reference', strtolower($csv));
    }
}
