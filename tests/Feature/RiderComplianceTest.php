<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Coverage for the rider compliance gating merged from the standalone
 * Logistics web: bearer-token revocation for suspended accounts, the
 * licence/capacity assignment gates, and the licence re-verification flow.
 *
 * Uses DatabaseTransactions; every row is rolled back. Protected by
 * Tests\TestCase::guardAgainstDestructiveDatabase().
 */
class RiderComplianceTest extends TestCase
{
    use DatabaseTransactions;

    private function riderUser(array $riderExtra = [], string $userStatus = 'active'): array
    {
        $email = 'rider.'.bin2hex(random_bytes(3)).'@test.local';

        $user = User::create([
            'name' => 'Compliance Rider',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => $userStatus,
        ]);

        $rider = Rider::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Compliance Rider',
            'email' => $email,
            'phone' => '09171234567',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'is_online' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ], $riderExtra));

        return [$user->fresh(), $rider];
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Logistics Admin',
            'email' => 'admin.'.bin2hex(random_bytes(3)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'logistics_admin',
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);
    }

    private function delivery(Rider $rider, string $status = 'assigned', array $extra = []): Delivery
    {
        return Delivery::create(array_merge([
            'rider_id' => $rider->id,
            'status' => $status,
            'tracking_number' => 'TRK-'.strtoupper(bin2hex(random_bytes(3))),
            'sender_name' => 'Sender',
            'recipient_name' => 'Recipient',
            'sender_address' => 'Somewhere',
            'recipient_address' => 'Elsewhere',
        ], $extra));
    }

    // ------------------------------------------------ token revocation

    public function test_a_suspended_rider_token_is_rejected(): void
    {
        [$user] = $this->riderUser([], 'suspended');

        Sanctum::actingAs($user);

        $this->getJson('/api/logistics/rider/profile')
            ->assertStatus(403);
    }

    public function test_an_active_rider_token_is_accepted(): void
    {
        [$user] = $this->riderUser();

        Sanctum::actingAs($user);

        $this->getJson('/api/logistics/rider/profile')->assertOk();
    }

    // ------------------------------------------------ pre-pickup licence gate

    public function test_an_expired_licence_blocks_accepting_a_delivery(): void
    {
        [$user, $rider] = $this->riderUser(['license_expiry' => now()->subDay()->format('Y-m-d')]);
        $delivery = $this->delivery($rider, 'assigned');

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/accept")
            ->assertStatus(403)
            ->assertJsonValidationErrors('status');

        $this->assertSame('assigned', $delivery->fresh()->status);
    }

    public function test_a_compliant_rider_can_accept_a_delivery(): void
    {
        [$user, $rider] = $this->riderUser();
        $delivery = $this->delivery($rider, 'assigned');

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/accept")
            ->assertOk();

        $this->assertSame('accepted', $delivery->fresh()->status);
    }

    // ------------------------------------------------ capacity helpers

    public function test_capacity_helpers_count_only_active_deliveries(): void
    {
        [, $rider] = $this->riderUser();

        $this->delivery($rider, 'assigned');
        $this->delivery($rider, 'out_for_delivery');
        $this->delivery($rider, 'arrived_at_customer');
        $this->delivery($rider, 'delivered');

        $rider = $rider->fresh();

        $this->assertSame(3, $rider->activeDeliveryCount());
        $this->assertFalse($rider->canAcceptMoreDeliveries());
        $this->assertSame(Rider::MAX_ACTIVE_DELIVERIES, 3);
    }

    // ------------------------------------------------ web assignment gate

    public function test_web_assignment_rejects_an_expired_licence(): void
    {
        $admin = $this->admin();
        [, $rider] = $this->riderUser(['license_expiry' => now()->subDay()->format('Y-m-d')]);
        $delivery = $this->delivery($rider, 'waiting_for_rider', ['rider_id' => null]);

        $this->actingAs($admin)
            ->post("/logistics/deliveries/{$delivery->id}/assign-rider", ['rider_id' => $rider->id])
            ->assertSessionHasErrors('rider_id');

        $this->assertNull($delivery->fresh()->rider_id);
    }

    // ------------------------------------------------ re-verification

    public function test_reverify_clears_an_expired_licence(): void
    {
        $admin = $this->admin();
        [, $rider] = $this->riderUser([
            'license_expiry' => now()->subDay()->format('Y-m-d'),
            'vehicle_verification' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post("/logistics/riders/{$rider->id}/reverify", [
                'license_number' => 'N01-23-456789',
                'license_expiry' => now()->addYear()->format('Y-m-d'),
                'vehicle_registration' => 'ABC-1234',
            ])
            ->assertSessionHasNoErrors();

        $rider = $rider->fresh();

        $this->assertFalse($rider->licenseExpired());
        $this->assertSame('verified', $rider->vehicle_verification);
        $this->assertTrue((bool) $rider->is_verified);
    }
}
