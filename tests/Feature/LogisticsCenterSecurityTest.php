<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\Notification;
use App\Models\Rider;
use App\Models\ServiceArea;
use App\Models\User;
use App\Support\LogisticsAlerts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogisticsCenterSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function user(string $role, ?int $centerId = null): User
    {
        $this->seq++;

        return User::create([
            'name' => ucfirst($role).' '.$this->seq,
            'email' => "center-security-{$role}-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => $role,
            'status' => 'active',
            'account_status' => 'active',
            'center_id' => $centerId,
            'email_verified_at' => now(),
        ]);
    }

    private function center(string $name): LogisticsCenter
    {
        $this->seq++;

        return LogisticsCenter::create([
            'name' => $name.' '.$this->seq,
            'address' => '1 Security Street',
            'city' => 'Security City',
            'is_active' => true,
        ]);
    }

    private function delivery(?LogisticsCenter $center = null, array $overrides = []): Delivery
    {
        $this->seq++;

        return Delivery::create(array_merge([
            'tracking_number' => 'TRK-20261020-8'.str_pad((string) $this->seq, 3, '0', STR_PAD_LEFT),
            'sender_name' => 'Security Sender',
            'recipient_name' => 'Security Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'center_id' => $center?->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'pending_arrival',
            'weight' => 1,
        ], $overrides));
    }

    private function rider(LogisticsCenter $center, ?ServiceArea $area = null, string $name = 'Security Rider'): Rider
    {
        $this->seq++;

        return Rider::create([
            'center_id' => $center->id,
            'service_area_id' => $area?->id,
            'name' => $name.' '.$this->seq,
            'email' => "center-security-rider-{$this->seq}@test.local",
            'phone' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'vehicle_capacity_kg' => 50,
            'status' => 'available',
            'is_verified' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'is_online' => true,
        ]);
    }

    public function test_manual_assignment_uses_handling_center_when_destination_is_unset(): void
    {
        $centerA = $this->center('Assignment Center A');
        $centerB = $this->center('Assignment Center B');
        $staffA = $this->user('logistics_staff', $centerA->id);
        $delivery = $this->delivery($centerA, [
            'status' => 'waiting_for_rider',
            'destination_center_id' => null,
            'service_area_id' => null,
        ]);
        $otherRider = $this->rider($centerB, null, 'Other Center Rider');
        $ownRider = $this->rider($centerA, null, 'Own Center Rider');

        $this->actingAs($staffA)
            ->post("/logistics/deliveries/{$delivery->id}/assign-rider", ['rider_id' => $otherRider->id])
            ->assertRedirect()
            ->assertSessionHasErrors('rider_id');

        $this->assertNull($delivery->fresh()->rider_id);
        $this->assertSame('waiting_for_rider', $delivery->fresh()->status);

        $this->actingAs($staffA)
            ->post("/logistics/deliveries/{$delivery->id}/assign-rider", ['rider_id' => $ownRider->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($ownRider->id, $delivery->fresh()->rider_id);
        $this->assertSame('assigned', $delivery->fresh()->status);
    }

    public function test_auto_assignment_rejects_a_rider_from_another_handling_center(): void
    {
        $centerA = $this->center('Auto Center A');
        $centerB = $this->center('Auto Center B');
        $admin = User::where('email', 'logistics_admin@test.com')->firstOrFail();
        $delivery = $this->delivery($centerA, [
            'status' => 'waiting_for_rider',
            'destination_center_id' => null,
            'service_area_id' => null,
        ]);
        $otherRider = $this->rider($centerB, null, 'Auto Other Rider');

        $this->actingAs($admin)
            ->post("/logistics/deliveries/{$delivery->id}/auto-assign")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($delivery->fresh()->rider_id);
        $this->assertSame('waiting_for_rider', $delivery->fresh()->status);
        $this->assertNotSame($otherRider->id, $delivery->fresh()->rider_id);
    }

    public function test_global_admin_can_still_assign_a_completely_unrouted_delivery(): void
    {
        $center = $this->center('Onboarding Center');
        $admin = User::where('email', 'logistics_admin@test.com')->firstOrFail();
        $delivery = $this->delivery(null, [
            'status' => 'waiting_for_rider',
            'destination_center_id' => null,
            'service_area_id' => null,
        ]);
        $rider = $this->rider($center, null, 'Onboarding Rider');

        $this->actingAs($admin)
            ->post("/logistics/deliveries/{$delivery->id}/assign-rider", ['rider_id' => $rider->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($rider->id, $delivery->fresh()->rider_id);
        $this->assertSame('assigned', $delivery->fresh()->status);
    }

    public function test_center_staff_cannot_access_or_mutate_an_unrouted_delivery(): void
    {
        $center = $this->center('Unrouted Guard Center');
        $staff = $this->user('logistics_staff', $center->id);
        $admin = User::where('email', 'logistics_admin@test.com')->firstOrFail();
        $delivery = $this->delivery(null);

        $this->actingAs($staff)
            ->get("/logistics/deliveries/{$delivery->id}")
            ->assertForbidden();

        $this->actingAs($staff)
            ->get("/logistics/deliveries/{$delivery->id}/edit")
            ->assertForbidden();

        $this->actingAs($staff)
            ->post("/logistics/deliveries/{$delivery->id}/assign-rider", ['rider_id' => 1])
            ->assertForbidden();

        $this->actingAs($staff)
            ->post("/logistics/deliveries/{$delivery->id}/auto-assign")
            ->assertForbidden();

        $this->actingAs($staff)
            ->post("/logistics/deliveries/{$delivery->id}/archive")
            ->assertForbidden();

        $this->actingAs($admin)
            ->get("/logistics/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee($delivery->tracking_number);
    }

    public function test_staff_without_a_center_see_no_delivery_data_and_cannot_open_details(): void
    {
        $centerA = $this->center('No Center A');
        $centerB = $this->center('No Center B');
        $staff = $this->user('logistics_staff');
        $deliveryA = $this->delivery($centerA);
        $deliveryB = $this->delivery($centerB);
        $archivedA = $this->delivery($centerA, ['archived_at' => now()]);

        $index = $this->actingAs($staff)
            ->get("/logistics/deliveries?center_id={$centerA->id}")
            ->assertOk()
            ->assertDontSee($deliveryA->tracking_number)
            ->assertDontSee($deliveryB->tracking_number);

        $this->assertSame(0, $index->viewData('deliveries')->total());
        $this->assertSame(0, $index->viewData('archivedCount'));
        $this->assertTrue($index->viewData('filterCenters')->isEmpty());
        $this->assertTrue($index->viewData('filterRiders')->isEmpty());
        $this->assertTrue($index->viewData('filterServiceAreas')->isEmpty());

        $archived = $this->actingAs($staff)
            ->get('/logistics/deliveries-archived')
            ->assertOk()
            ->assertDontSee($archivedA->tracking_number);

        $this->assertSame(0, $archived->viewData('deliveries')->total());

        $dashboard = $this->actingAs($staff)
            ->get('/logistics/dashboard')
            ->assertOk()
            ->assertDontSee($deliveryA->tracking_number)
            ->assertDontSee($deliveryB->tracking_number);

        $this->assertSame(0, $dashboard->viewData('deliveries')['total']);
        $this->assertSame(0, $dashboard->viewData('riders')['total']);
        $this->assertCount(0, $dashboard->viewData('detailedRecords'));
        $this->assertSame([], $dashboard->viewData('operationalAlerts'));

        $this->actingAs($staff)
            ->get('/logistics/sorting-center')
            ->assertOk()
            ->assertDontSee($deliveryA->tracking_number)
            ->assertDontSee($deliveryB->tracking_number);

        $this->actingAs($staff)
            ->get("/logistics/deliveries/{$deliveryA->id}")
            ->assertForbidden();

        $this->actingAs($staff)
            ->post("/logistics/deliveries/{$deliveryA->id}/receive", ['center_id' => $centerA->id])
            ->assertForbidden();

        $this->actingAs($staff)
            ->get('/logistics/deliveries/create')
            ->assertForbidden();

        $this->assertSame([], LogisticsAlerts::forUser($staff));
    }

    public function test_global_admin_still_sees_all_centers_and_unrouted_deliveries(): void
    {
        $centerA = $this->center('Global Admin A');
        $centerB = $this->center('Global Admin B');
        $admin = User::where('email', 'logistics_admin@test.com')->firstOrFail();
        $deliveryA = $this->delivery($centerA);
        $deliveryB = $this->delivery($centerB);
        $unrouted = $this->delivery(null);
        $archived = $this->delivery($centerA, ['archived_at' => now()]);

        $this->actingAs($admin)
            ->get('/logistics/deliveries')
            ->assertOk()
            ->assertSee($deliveryA->tracking_number)
            ->assertSee($deliveryB->tracking_number)
            ->assertSee($unrouted->tracking_number);

        $this->actingAs($admin)
            ->get('/logistics/deliveries-archived')
            ->assertOk()
            ->assertSee($archived->tracking_number);

        $dashboard = $this->actingAs($admin)
            ->get('/logistics/dashboard')
            ->assertOk();

        $expectedTotal = Delivery::notArchived()->count();
        $this->assertGreaterThanOrEqual(3, $expectedTotal);
        $this->assertSame($expectedTotal, $dashboard->viewData('deliveries')['total']);
    }

    public function test_staff_without_a_center_cannot_reach_center_notifications(): void
    {
        $center = $this->center('Notification No Center');
        $staff = $this->user('logistics_staff');
        $global = Notification::create([
            'type' => 'new_delivery_request',
            'title' => 'No Center Global',
            'message' => 'Global notice',
            'icon' => 'pickup',
            'priority' => 'normal',
            'audience' => 'global',
        ]);
        $centerNotice = Notification::create([
            'type' => 'new_delivery_request',
            'title' => 'No Center Center Notice',
            'message' => 'Center notice',
            'icon' => 'pickup',
            'priority' => 'normal',
            'center_id' => $center->id,
            'audience' => 'center',
        ]);

        $this->actingAs($staff)
            ->getJson('/logistics/notifications')
            ->assertOk()
            ->assertJsonFragment(['title' => 'No Center Global'])
            ->assertJsonMissing(['title' => 'No Center Center Notice']);

        $this->actingAs($staff)
            ->patchJson("/logistics/notifications/{$centerNotice->id}/read")
            ->assertNotFound();

        $this->assertFalse($centerNotice->fresh()->is_read);
        $this->assertFalse($global->fresh()->is_read);
    }
}
