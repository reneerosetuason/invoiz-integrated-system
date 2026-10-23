<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsCenter;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SortingCenterTrackingTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::create([
            'name' => 'Hub Admin',
            'email' => 'hub-admin-'.bin2hex(random_bytes(4)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function staff(int $centerId): User
    {
        return User::create([
            'name' => 'Hub Staff',
            'email' => 'hub-staff-'.bin2hex(random_bytes(4)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'staff',
            'center_id' => $centerId,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function center(string $name): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name,
            'address' => 'Hub Address',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'is_active' => true,
        ]);
    }

    private function delivery(array $extra = []): Delivery
    {
        return Delivery::create(array_merge([
            'status' => 'assigned',
            'parcel_status' => 'received',
            'tracking_number' => 'TRK-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(2))),
            'sender_name' => 'Hub Sender',
            'recipient_name' => 'Hub Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
        ], $extra));
    }

    public function test_tracking_page_exposes_existing_parcel_milestones_without_changing_delivery_status(): void
    {
        $delivery = $this->delivery([
            'status' => 'out_for_delivery',
            'parcel_status' => 'scanned',
            'scanned_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('tracking.search', ['tracking_number' => $delivery->tracking_number]))
            ->assertOk()
            ->assertSee('Parcel Processing')
            ->assertSee('At Hub')
            ->assertSee('Scanned')
            ->assertSee('Out for Delivery')
            ->assertDontSee('Final Status');
    }

    public function test_duplicate_scan_is_idempotent_and_does_not_write_another_log(): void
    {
        $delivery = $this->delivery([
            'parcel_status' => 'scanned',
            'scanned_at' => now(),
        ]);

        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status' => 'scanned',
            'notes' => 'Parcel scanned and verified.',
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('deliveries.scan-verify'), [
                'tracking_number' => $delivery->tracking_number,
            ])
            ->assertOk()
            ->assertJsonPath('already', true);

        $this->assertSame(
            1,
            DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'scanned')->count(),
        );
    }

    public function test_unknown_tracking_number_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('deliveries.scan-verify'), [
                'tracking_number' => 'TRK-'.now()->format('Ymd').'-ZZZZ',
            ])
            ->assertNotFound()
            ->assertJsonPath('error', 'not_found');
    }

    public function test_cross_center_scan_is_forbidden(): void
    {
        $centerA = $this->center('Center A');
        $centerB = $this->center('Center B');
        $delivery = $this->delivery(['center_id' => $centerB->id]);

        $this->actingAs($this->staff($centerA->id))
            ->postJson(route('deliveries.scan-verify'), [
                'tracking_number' => $delivery->tracking_number,
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'forbidden');
    }

    public function test_sorting_center_service_area_filter_is_applied_server_side(): void
    {
        $center = $this->center('Filter Center');
        $areaA = ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => 'Area A',
            'is_active' => true,
        ]);
        $areaB = ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => 'Area B',
            'is_active' => true,
        ]);

        $inAreaA = $this->delivery([
            'center_id' => $center->id,
            'service_area_id' => $areaA->id,
        ]);
        $inAreaB = $this->delivery([
            'center_id' => $center->id,
            'service_area_id' => $areaB->id,
        ]);

        $this->actingAs($this->admin())
            ->get(route('sorting-center.index', ['service_area_id' => $areaA->id]))
            ->assertOk()
            ->assertSee($inAreaA->tracking_number)
            ->assertDontSee($inAreaB->tracking_number);
    }
}
