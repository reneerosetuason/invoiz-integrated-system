<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Hub Transport Driver application phone uniqueness.
 *
 * An existing driver may reuse their own phone number when re-submitting under
 * their own login email, while another driver's number, a cross-role number,
 * and phone variants that normalize to the same digits keep failing.
 *
 * The tables required by the validation rules are created on the in-memory
 * SQLite connection only; no migrations run and no MySQL instance is used.
 */
class HubDriverApplicationPhoneUniquenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The self-contained schema is only created on the in-memory SQLite test connection.');
        }

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('role')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::dropIfExists('sellers');
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('hub_transport_drivers');
        Schema::create('hub_transport_drivers', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('phone');
            $table->string('license_number')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('riders');
        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('phone');
            $table->string('license_number')->nullable();
            $table->string('license_plate')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('vehicle_types');
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('label');
            $table->decimal('capacity_kg', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
        });

        DB::table('vehicle_types')->insert([
            ['name' => 'motorcycle', 'label' => 'Motorcycle', 'capacity_kg' => 30, 'is_active' => true, 'sort_order' => 1],
            ['name' => 'truck', 'label' => 'Truck', 'capacity_kg' => 2000, 'is_active' => true, 'sort_order' => 2],
        ]);

        Schema::dropIfExists('rider_applications');
        Schema::create('rider_applications', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('license_number')->nullable();
            $table->string('license_plate')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    private function hubDriver(string $email, string $phone): int
    {
        return DB::table('hub_transport_drivers')->insertGetId([
            'email' => $email,
            'phone' => $phone,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function hubApplication(array $overrides = []): array
    {
        return array_merge([
            'application_type' => 'hub_transport_driver',
            'vehicle_ownership' => 'company',
            'name' => 'Juan Dela Cruz',
            'sex' => 'male',
            'birthday' => '1990-01-01',
            'address' => '123 Sample Street, Quezon City',
            'email' => 'new-applicant@test.local',
            'phone' => '09170000001',
            'license_number' => 'N01-90-000001',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'accepted_terms' => '1',
            'documents' => [
                'valid_id' => UploadedFile::fake()->create('valid-id.pdf', 12, 'application/pdf'),
                'drivers_license' => UploadedFile::fake()->create('license.pdf', 12, 'application/pdf'),
            ],
        ], $overrides);
    }

    public function test_driver_may_reuse_their_own_phone_when_resubmitting_under_their_own_email(): void
    {
        $this->hubDriver('own-driver@test.local', '09171111111');

        $response = $this->post('/api/logistics/rider/apply', $this->hubApplication([
            'email' => 'own-driver@test.local',
            'phone' => '09171111111',
        ]));

        $response->assertStatus(422);
        $response->assertJsonMissingValidationErrors('phone');
        // The existing driver record still blocks a second account.
        $response->assertJsonValidationErrors('email');
        $this->assertSame(0, DB::table('rider_applications')->count());
    }

    public function test_another_driver_phone_is_still_rejected(): void
    {
        $this->hubDriver('first-driver@test.local', '09172222222');

        $response = $this->post('/api/logistics/rider/apply', $this->hubApplication([
            'email' => 'second-driver@test.local',
            'phone' => '09172222222',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone');
        $this->assertSame(0, DB::table('rider_applications')->count());
    }

    public function test_phone_registered_to_a_delivery_rider_is_still_rejected_for_a_hub_driver(): void
    {
        DB::table('riders')->insert([
            'email' => 'delivery-rider@test.local',
            'phone' => '09173333333',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/api/logistics/rider/apply', $this->hubApplication([
            'email' => 'third-driver@test.local',
            'phone' => '09173333333',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone');
        $this->assertStringContainsString('Delivery Rider', (string) $response->json('errors.phone.0'));
    }

    public function test_phone_registered_to_a_hub_driver_is_still_rejected_for_a_delivery_rider(): void
    {
        $this->hubDriver('hub-driver@test.local', '09174444444');

        $response = $this->post('/api/logistics/rider/apply', [
            'application_type' => 'delivery_rider',
            'vehicle_ownership' => 'own',
            'name' => 'Maria Santos',
            'sex' => 'female',
            'birthday' => '1992-05-05',
            'address' => '45 Sample Avenue, Manila',
            'email' => 'delivery-applicant@test.local',
            'phone' => '09174444444',
            'license_number' => 'N02-90-000002',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'license_plate' => 'ABC-1234',
            'vehicle_type' => 'motorcycle',
            'accepted_terms' => '1',
            'documents' => [
                'valid_id' => UploadedFile::fake()->create('valid-id.pdf', 12, 'application/pdf'),
                'drivers_license' => UploadedFile::fake()->create('license.pdf', 12, 'application/pdf'),
                'vehicle_registration' => UploadedFile::fake()->create('registration.pdf', 12, 'application/pdf'),
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone');
        $this->assertStringContainsString('Hub Transport Driver', (string) $response->json('errors.phone.0'));
    }

    public function test_equivalent_phone_format_is_normalized_before_the_conflict_check(): void
    {
        $this->hubDriver('first-driver@test.local', '09175555555');

        $response = $this->post('/api/logistics/rider/apply', $this->hubApplication([
            'email' => 'fourth-driver@test.local',
            'phone' => '+63 917 555 5555',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone');
    }

    public function test_own_phone_variant_is_normalized_and_still_accepted(): void
    {
        $this->hubDriver('own-driver@test.local', '09176666666');

        $response = $this->post('/api/logistics/rider/apply', $this->hubApplication([
            'email' => 'own-driver@test.local',
            'phone' => '+639176666666',
        ]));

        $response->assertStatus(422);
        $response->assertJsonMissingValidationErrors('phone');
        $response->assertJsonValidationErrors('email');
    }
}
