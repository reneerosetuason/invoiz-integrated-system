<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Rider;
use App\Models\RiderLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DispatchDistanceTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::create([
            'name' => 'Dispatch Admin',
            'email' => 'dispatch-admin-'.bin2hex(random_bytes(4)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'logistics_admin',
            'is_admin' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function rider(string $name): Rider
    {
        $email = strtolower(str_replace(' ', '.', $name)).'-'.bin2hex(random_bytes(4)).'@test.local';

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        return Rider::create([
            'user_id' => $user->id,
            'name' => $name,
            'email' => $email,
            'phone' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'is_online' => true,
            'is_verified' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ]);
    }

    private function delivery(array $extra = []): Delivery
    {
        return Delivery::create(array_merge([
            'status' => 'waiting_for_rider',
            'tracking_number' => 'TRK-DISPATCH-'.strtoupper(bin2hex(random_bytes(4))),
            'sender_name' => 'Dispatch Sender',
            'recipient_name' => 'Dispatch Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'payment_method' => 'prepaid',
            'sender_lat' => 14.5995000,
            'sender_lng' => 120.9842000,
        ], $extra));
    }

    public function test_auto_assign_prefers_the_nearest_fresh_rider_over_a_less_loaded_far_rider(): void
    {
        $admin = $this->admin();
        $near = $this->rider('Near Rider');
        $far = $this->rider('Far Rider');

        RiderLocation::create([
            'rider_id' => $near->id,
            'latitude' => 14.6005000,
            'longitude' => 120.9842000,
            'recorded_at' => now()->subMinute(),
        ]);
        RiderLocation::create([
            'rider_id' => $far->id,
            'latitude' => 14.7000000,
            'longitude' => 121.1000000,
            'recorded_at' => now()->subMinute(),
        ]);

        $this->delivery(['rider_id' => $near->id, 'status' => 'assigned']);

        $delivery = $this->delivery();

        $this->actingAs($admin)
            ->post(route('deliveries.auto-assign', $delivery))
            ->assertRedirect();

        $this->assertSame($near->id, $delivery->fresh()->rider_id);
    }

    public function test_auto_assign_falls_back_to_workload_when_locations_are_stale(): void
    {
        $admin = $this->admin();
        $busyNear = $this->rider('Busy Near Rider');
        $idleFar = $this->rider('Idle Far Rider');

        RiderLocation::create([
            'rider_id' => $busyNear->id,
            'latitude' => 14.6005000,
            'longitude' => 120.9842000,
            'recorded_at' => now()->subHours(2),
        ]);
        RiderLocation::create([
            'rider_id' => $idleFar->id,
            'latitude' => 14.9000000,
            'longitude' => 121.3000000,
            'recorded_at' => now()->subHours(2),
        ]);

        $this->delivery(['rider_id' => $busyNear->id, 'status' => 'assigned']);

        $delivery = $this->delivery();

        $this->actingAs($admin)
            ->post(route('deliveries.auto-assign', $delivery))
            ->assertRedirect();

        $this->assertSame($idleFar->id, $delivery->fresh()->rider_id);
    }

    public function test_auto_assign_uses_destination_when_pickup_coordinates_are_missing(): void
    {
        $admin = $this->admin();
        $nearDestination = $this->rider('Near Destination Rider');
        $far = $this->rider('Far From Destination Rider');

        RiderLocation::create([
            'rider_id' => $nearDestination->id,
            'latitude' => 14.7001000,
            'longitude' => 121.1001000,
            'recorded_at' => now()->subMinute(),
        ]);
        RiderLocation::create([
            'rider_id' => $far->id,
            'latitude' => 14.5000000,
            'longitude' => 120.9000000,
            'recorded_at' => now()->subMinute(),
        ]);

        $this->delivery(['rider_id' => $far->id, 'status' => 'assigned']);

        $delivery = $this->delivery([
            'sender_lat' => null,
            'sender_lng' => null,
            'recipient_lat' => 14.7000000,
            'recipient_lng' => 121.1000000,
        ]);

        $this->actingAs($admin)
            ->post(route('deliveries.auto-assign', $delivery))
            ->assertRedirect();

        $this->assertSame($nearDestination->id, $delivery->fresh()->rider_id);
    }

    public function test_manual_assignment_uses_the_shared_locked_assignment_path(): void
    {
        $admin = $this->admin();
        $rider = $this->rider('Manual Rider');
        $delivery = $this->delivery();

        $this->actingAs($admin)
            ->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $rider->id])
            ->assertRedirect();

        $delivery->refresh();

        $this->assertSame($rider->id, $delivery->rider_id);
        $this->assertSame('assigned', $delivery->status);
        $this->assertNotNull($delivery->assigned_at);
    }

    public function test_bulk_assignment_assigns_each_delivery_and_suppresses_duplicate_ids(): void
    {
        $admin = $this->admin();
        $rider = $this->rider('Bulk Rider');
        $first = $this->delivery();
        $second = $this->delivery();

        $response = $this->actingAs($admin)->postJson(route('deliveries.bulk-assign'), [
            'rider_id' => $rider->id,
            'delivery_ids' => [$first->id, $second->id, $first->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('assigned', 2)
            ->assertJsonPath('failed', 0)
            ->assertJsonCount(2, 'results');

        $this->assertSame($rider->id, $first->fresh()->rider_id);
        $this->assertSame($rider->id, $second->fresh()->rider_id);
    }

    public function test_bulk_assignment_returns_per_delivery_results_for_mixed_success(): void
    {
        $admin = $this->admin();
        $rider = $this->rider('Mixed Bulk Rider');
        $assignable = $this->delivery();
        $delivered = $this->delivery(['status' => 'delivered']);

        $response = $this->actingAs($admin)->postJson(route('deliveries.bulk-assign'), [
            'rider_id' => $rider->id,
            'delivery_ids' => [$assignable->id, $delivered->id],
        ]);

        $response->assertStatus(207)
            ->assertJsonPath('assigned', 1)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('results.0.status', 'assigned')
            ->assertJsonPath('results.1.status', 'failed');

        $this->assertSame($rider->id, $assignable->fresh()->rider_id);
        $this->assertNull($delivered->fresh()->rider_id);
    }

    public function test_bulk_assignment_shows_per_delivery_results_after_redirect(): void
    {
        $admin = $this->admin();
        $rider = $this->rider('Results Bulk Rider');
        $assignable = $this->delivery();
        $delivered = $this->delivery(['status' => 'delivered']);

        $this->actingAs($admin)
            ->post(route('deliveries.bulk-assign'), [
                'rider_id' => $rider->id,
                'delivery_ids' => [$assignable->id, $delivered->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('bulk_assignment_results', fn ($results) => count($results) === 2);

        $this->get(route('deliveries.index'))
            ->assertOk()
            ->assertSee('Bulk assignment results')
            ->assertSee($assignable->tracking_number)
            ->assertSee('Delivery #'.$delivered->id);
    }

    public function test_bulk_assignment_does_not_roll_back_successful_rows_when_capacity_is_reached(): void
    {
        $admin = $this->admin();
        $rider = $this->rider('Capacity Bulk Rider');
        $deliveries = collect(range(1, 4))->map(fn () => $this->delivery());

        $response = $this->actingAs($admin)->postJson(route('deliveries.bulk-assign'), [
            'rider_id' => $rider->id,
            'delivery_ids' => $deliveries->pluck('id')->all(),
        ]);

        $response->assertStatus(207)
            ->assertJsonPath('assigned', Rider::MAX_ACTIVE_DELIVERIES)
            ->assertJsonPath('failed', 1);

        $this->assertSame(
            Rider::MAX_ACTIVE_DELIVERIES,
            $deliveries->filter(fn (Delivery $delivery) => $delivery->fresh()->rider_id === $rider->id)->count(),
        );
    }

    public function test_a_delivery_can_be_reassigned_before_pickup_while_en_route(): void
    {
        $admin = $this->admin();

        foreach (['accepted', 'going_to_pickup', 'arrived_at_shop'] as $status) {
            $original = $this->rider('Original '.$status);
            $replacement = $this->rider('Replacement '.$status);
            $delivery = $this->delivery(['rider_id' => $original->id, 'status' => $status]);

            $this->actingAs($admin)
                ->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $replacement->id])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $delivery->refresh();

            $this->assertSame($replacement->id, $delivery->rider_id, "reassignment must be allowed before pickup for status {$status}");
            $this->assertSame('assigned', $delivery->status);
        }
    }

    public function test_a_delivery_cannot_be_reassigned_once_picked_up(): void
    {
        $admin = $this->admin();
        $original = $this->rider('In Transit Rider');
        $replacement = $this->rider('Late Replacement Rider');
        $delivery = $this->delivery(['rider_id' => $original->id, 'status' => 'picked_up']);

        $this->actingAs($admin)
            ->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $replacement->id])
            ->assertRedirect()
            ->assertSessionHasErrors('rider_id');

        $this->assertSame($original->id, $delivery->fresh()->rider_id);
        $this->assertSame('picked_up', $delivery->fresh()->status);
    }

    public function test_reassigning_the_same_rider_mid_job_is_a_no_op(): void
    {
        $admin = $this->admin();
        $rider = $this->rider('Same Rider');
        $delivery = $this->delivery(['rider_id' => $rider->id, 'status' => 'accepted']);

        $this->actingAs($admin)
            ->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $rider->id])
            ->assertRedirect();

        $delivery->refresh();

        $this->assertSame($rider->id, $delivery->rider_id);
        $this->assertSame('accepted', $delivery->status, 'the same rider must not be reset back to assigned');
    }
}
