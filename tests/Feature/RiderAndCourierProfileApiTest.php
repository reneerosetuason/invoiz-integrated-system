<?php

namespace Tests\Feature;

use App\Models\HubTransportDriver;
use App\Models\LogisticsCompany;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderAndCourierProfileApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('migrate');
    }

    private function createRider(): array
    {
        $email = 'rider.' . bin2hex(random_bytes(3)) . '@test.local';

        $user = User::create([
            'name' => 'Juan Rider',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'rider',
            'status' => 'active',
            'phone' => '09171112222',
        ]);

        $rider = Rider::create([
            'user_id' => $user->id,
            'name' => 'Juan Rider',
            'email' => $email,
            'phone' => '09171112222',
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'ABC 1234',
            'status' => 'available',
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
        ]);

        return [$user, $rider];
    }

    private function createCourier(): array
    {
        $company = LogisticsCompany::create([
            'name' => 'Inter-Hub Freight Express',
            'status' => 'active',
        ]);

        $email = 'courier.' . bin2hex(random_bytes(3)) . '@test.local';

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Driver Pedro',
            'email' => $email,
            'password' => 'password123',
            'phone' => '09183334444',
            'license_number' => 'N01-99-888888',
            'status' => 'active',
            'is_online' => false,
        ]);

        return [$company, $driver];
    }

    public function test_rider_can_update_phone_number(): void
    {
        [$user, $rider] = $this->createRider();
        Sanctum::actingAs($user, ['rider']);

        $response = $this->patchJson('/api/logistics/rider/profile', [
            'phone' => '09179998888',
        ]);

        $response->assertOk();
        $response->assertJsonPath('rider.phone', '09179998888');

        $rider->refresh();
        $this->assertEquals('09179998888', $rider->phone);
        $this->assertEquals('09179998888', $user->fresh()->phone);
    }

    public function test_rider_cannot_change_vehicle_without_admin_review(): void
    {
        [$user, $rider] = $this->createRider();
        Sanctum::actingAs($user, ['rider']);

        $response = $this->patchJson('/api/logistics/rider/profile', [
            'vehicle_type' => 'van',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Vehicle changes require logistics review and verification.']);
    }

    public function test_rider_can_upload_profile_photo(): void
    {
        [$user, $rider] = $this->createRider();
        Sanctum::actingAs($user, ['rider']);
        Storage::fake('public');

        $file = UploadedFile::fake()->create('avatar.jpg', 15, 'image/jpeg');

        $response = $this->postJson('/api/logistics/rider/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'rider' => ['avatar']]);

        $rider->refresh();
        $this->assertNotNull($rider->avatar);
        Storage::disk('public')->assertExists($rider->avatar);
    }

    public function test_hub_courier_can_update_phone_number(): void
    {
        [$company, $driver] = $this->createCourier();
        Sanctum::actingAs($driver, ['hub-courier']);

        $response = $this->patchJson('/api/logistics/courier/profile', [
            'phone' => '09187776666',
        ]);

        $response->assertOk();
        $response->assertJsonPath('courier.phone', '09187776666');

        $driver->refresh();
        $this->assertEquals('09187776666', $driver->phone);
    }

    public function test_hub_courier_can_upload_profile_photo(): void
    {
        [$company, $driver] = $this->createCourier();
        Sanctum::actingAs($driver, ['hub-courier']);
        Storage::fake('public');

        $file = UploadedFile::fake()->create('courier.png', 20, 'image/png');

        $response = $this->postJson('/api/logistics/courier/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'courier' => ['avatar']]);

        $driver->refresh();
        $this->assertNotNull($driver->avatar);
        Storage::disk('public')->assertExists($driver->avatar);
    }
}
