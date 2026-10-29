<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Rider;
use App\Models\RiderLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderLocationOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    private function riderUser(string $name): array
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

        $rider = Rider::create([
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

        return [$user, $rider];
    }

    private function delivery(Rider $rider): Delivery
    {
        return Delivery::create([
            'rider_id' => $rider->id,
            'status' => 'out_for_delivery',
            'tracking_number' => 'TRK-LOCATION-'.strtoupper(bin2hex(random_bytes(4))),
            'sender_name' => 'Location Sender',
            'recipient_name' => 'Location Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'payment_method' => 'prepaid',
        ]);
    }

    public function test_rider_can_report_location_for_an_assigned_delivery(): void
    {
        [$user, $rider] = $this->riderUser('Assigned Rider');
        $delivery = $this->delivery($rider);
        Sanctum::actingAs($user);

        $this->postJson('/api/logistics/rider/location', [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'delivery_id' => $delivery->id,
        ])->assertCreated();

        $this->assertDatabaseHas('rider_locations', [
            'rider_id' => $rider->id,
            'delivery_id' => $delivery->id,
        ]);
    }

    public function test_rider_cannot_report_location_against_another_riders_delivery(): void
    {
        [$user] = $this->riderUser('Requesting Rider');
        [, $otherRider] = $this->riderUser('Owning Rider');
        $delivery = $this->delivery($otherRider);
        Sanctum::actingAs($user);

        $this->postJson('/api/logistics/rider/location', [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'delivery_id' => $delivery->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_id');

        $this->assertSame(0, RiderLocation::where('delivery_id', $delivery->id)->count());
    }
}
