<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\RiderApplication;
use App\Models\RiderEmailVerification;
use App\Models\VehicleType;
use Database\Seeders\PsgcSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhilippineAddressDropdownTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        Artisan::call('migrate');

        RiderApplication::whereIn('email', [
            'mismatch_rider@invoiz.test',
            'mismatch_city@invoiz.test',
            'valid_psgc_rider@invoiz.test',
            'ncr_rider@invoiz.test',
            'hub_driver@invoiz.test',
        ])->forceDelete();

        // Ensure PSGC reference data is seeded in test DB
        if (Region::count() < 18) {
            $this->seed(PsgcSeeder::class);
        }

        // Ensure active vehicle types exist for delivery_rider and hub_transport_driver
        VehicleType::updateOrInsert(
            ['name' => 'motorcycle'],
            [
                'label' => 'Motorcycle',
                'is_active' => true,
                'capacity_kg' => 30,
                'sort_order' => 1,
            ]
        );
        VehicleType::updateOrInsert(
            ['name' => 'truck'],
            [
                'label' => 'Light Truck',
                'is_active' => true,
                'capacity_kg' => 1000,
                'sort_order' => 2,
            ]
        );
    }

    private function verifyRiderEmail(string $email): void
    {
        RiderEmailVerification::create([
            'email' => strtolower(trim($email)),
            'otp_hash' => 'dummy',
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => now(),
        ]);
    }

    public function test_all_18_regions_are_available(): void
    {
        $response = $this->getJson('/api/psgc/regions');
        $response->assertOk();

        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(18, $data);

        $codes = collect($data)->pluck('code')->all();
        $this->assertContains('130000000', $codes); // NCR
        $this->assertContains('030000000', $codes); // Central Luzon
        $this->assertContains('040000000', $codes); // CALABARZON
        $this->assertContains('070000000', $codes); // Central Visayas
        $this->assertContains('180000000', $codes); // NIR (Negros Island Region)
    }

    public function test_provinces_endpoint_reflects_psgc_provinces(): void
    {
        $provinceCount = Province::count();
        $this->assertGreaterThanOrEqual(82, $provinceCount);

        // CALABARZON (040000000) provinces test
        $response = $this->getJson('/api/psgc/regions/040000000/provinces');
        $response->assertOk();
        $provinces = $response->json();
        $names = collect($provinces)->pluck('name')->all();

        $this->assertContains('Batangas', $names);
        $this->assertContains('Cavite', $names);
        $this->assertContains('Laguna', $names);
        $this->assertContains('Quezon', $names);
        $this->assertContains('Rizal', $names);
    }

    public function test_ncr_has_no_provinces_and_lists_cities_directly(): void
    {
        // NCR (130000000) does not have provinces
        $provincesResponse = $this->getJson('/api/psgc/regions/130000000/provinces');
        $provincesResponse->assertOk();
        $this->assertSame([], $provincesResponse->json());

        // NCR cities/municipalities belong directly under region
        $citiesResponse = $this->getJson('/api/psgc/regions/130000000/cities');
        $citiesResponse->assertOk();
        $cities = $citiesResponse->json();
        $this->assertCount(17, $cities);

        $cityNames = collect($cities)->pluck('name')->all();
        $this->assertContains('City of Manila', $cityNames);
        $this->assertContains('Quezon City', $cityNames);
        $this->assertContains('City of Makati', $cityNames);
        $this->assertContains('Pateros', $cityNames);
    }

    public function test_cascading_cities_and_barangays_match_hierarchy(): void
    {
        // Laguna province code in PSGC is 043400000
        $citiesResponse = $this->getJson('/api/psgc/provinces/043400000/cities-municipalities');
        $citiesResponse->assertOk();
        $cities = $citiesResponse->json();
        $cityNames = collect($cities)->pluck('name')->all();
        $this->assertContains('City of San Pablo', $cityNames);

        // San Pablo City code in PSGC is 043424000
        $barangaysResponse = $this->getJson('/api/psgc/cities-municipalities/043424000/barangays');
        $barangaysResponse->assertOk();
        $barangays = $barangaysResponse->json();
        $this->assertNotEmpty($barangays);
        $brgyNames = collect($barangays)->pluck('name')->all();
        $this->assertContains('Bagong Bayan II-A (Pob.)', $brgyNames);
    }

    public function test_rider_application_rejects_mismatched_geographic_hierarchy(): void
    {
        $email = 'mismatch_rider@invoiz.test';
        $this->verifyRiderEmail($email);

        $payload = [
            'name' => 'Juan Dela Cruz',
            'email' => $email,
            'phone' => '09171234567',
            'sex' => 'male',
            'birthday' => '1995-05-15',
            'accepted_terms' => '1',
            'address' => 'Sample Street, San Pablo City, Laguna',
            'region_code' => '040000000', // CALABARZON
            'province_code' => '031400000', // Bulacan (Central Luzon - WRONG PROVINCE FOR CALABARZON!)
            'municipality_code' => '043424000',
            'barangay_code' => '043424001',
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'ABC1234',
            'license_number' => 'N01-23-456789',
            'license_expiry' => date('Y-m-d', strtotime('+1 year')),
            'vehicle_registration' => 'VR123456',
            'documents' => [
                'valid_id' => UploadedFile::fake()->create('id.jpg', 100),
                'drivers_license' => UploadedFile::fake()->create('license.jpg', 100),
                'vehicle_registration' => UploadedFile::fake()->create('orcr.jpg', 100),
            ],
        ];

        $response = $this->postJson('/api/logistics/rider/apply', $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['province_code']);
    }

    public function test_rider_application_rejects_mismatched_city_for_province(): void
    {
        $email = 'mismatch_city@invoiz.test';
        $this->verifyRiderEmail($email);

        $payload = [
            'name' => 'Juan Dela Cruz',
            'email' => $email,
            'phone' => '09171234567',
            'sex' => 'male',
            'birthday' => '1995-05-15',
            'accepted_terms' => '1',
            'address' => 'Sample Street',
            'region_code' => '040000000', // CALABARZON
            'province_code' => '043400000', // Laguna
            'municipality_code' => '137401000', // Mandaluyong (NCR - WRONG FOR LAGUNA!)
            'barangay_code' => '043424001',
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'ABC1235',
            'license_number' => 'N01-23-456780',
            'license_expiry' => date('Y-m-d', strtotime('+1 year')),
            'vehicle_registration' => 'VR123457',
            'documents' => [
                'valid_id' => UploadedFile::fake()->create('id.jpg', 100),
                'drivers_license' => UploadedFile::fake()->create('license.jpg', 100),
                'vehicle_registration' => UploadedFile::fake()->create('orcr.jpg', 100),
            ],
        ];

        $response = $this->postJson('/api/logistics/rider/apply', $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['municipality_code']);
    }

    public function test_rider_application_submits_and_persists_hierarchical_psgc_address(): void
    {
        $email = 'valid_psgc_rider@invoiz.test';
        $this->verifyRiderEmail($email);

        $payload = [
            'name' => 'Maria Santos',
            'email' => $email,
            'phone' => '09181234567',
            'sex' => 'female',
            'birthday' => '1996-08-20',
            'accepted_terms' => '1',
            'house_number' => '123',
            'street' => 'Rizal Avenue',
            'address' => '123, Rizal Avenue, Bagong Bayan II-A (Pob.), City of San Pablo, Laguna, CALABARZON',
            'region' => 'CALABARZON',
            'region_code' => '040000000',
            'province' => 'Laguna',
            'province_code' => '043400000',
            'municipality' => 'City of San Pablo',
            'municipality_code' => '043424000',
            'barangay' => 'Bagong Bayan II-A (Pob.)',
            'barangay_code' => '043424001',
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'XYZ9876',
            'license_number' => 'N02-98-765432',
            'license_expiry' => date('Y-m-d', strtotime('+1 year')),
            'vehicle_registration' => 'VR987654',
            'documents' => [
                'valid_id' => UploadedFile::fake()->create('id.jpg', 100),
                'drivers_license' => UploadedFile::fake()->create('license.jpg', 100),
                'vehicle_registration' => UploadedFile::fake()->create('orcr.jpg', 100),
            ],
        ];

        $response = $this->postJson('/api/logistics/rider/apply', $payload);
        $response->assertStatus(201);

        $application = RiderApplication::where('email', $email)->first();
        $this->assertNotNull($application);
        $this->assertSame('CALABARZON', $application->region);
        $this->assertSame('040000000', $application->region_code);
        $this->assertSame('Laguna', $application->province);
        $this->assertSame('043400000', $application->province_code);
        $this->assertSame('City of San Pablo', $application->municipality);
        $this->assertSame('043424000', $application->municipality_code);
        $this->assertSame('Bagong Bayan II-A (Pob.)', $application->barangay);
        $this->assertSame('043424001', $application->barangay_code);
    }

    public function test_ncr_rider_application_submits_without_province(): void
    {
        $email = 'ncr_rider@invoiz.test';
        $this->verifyRiderEmail($email);

        $payload = [
            'name' => 'Carlos Mendoza',
            'email' => $email,
            'phone' => '09191234567',
            'sex' => 'male',
            'birthday' => '1992-03-10',
            'accepted_terms' => '1',
            'house_number' => '45',
            'street' => 'Shaw Blvd',
            'address' => '45, Shaw Blvd, Addition Hills, City of Mandaluyong, National Capital Region (NCR)',
            'region' => 'National Capital Region (NCR)',
            'region_code' => '130000000',
            'province' => null,
            'province_code' => null,
            'municipality' => 'City of Mandaluyong',
            'municipality_code' => '137401000',
            'barangay' => 'Addition Hills',
            'barangay_code' => '137401001',
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'NCR1122',
            'license_number' => 'N03-11-223344',
            'license_expiry' => date('Y-m-d', strtotime('+1 year')),
            'vehicle_registration' => 'VR112233',
            'documents' => [
                'valid_id' => UploadedFile::fake()->create('id.jpg', 100),
                'drivers_license' => UploadedFile::fake()->create('license.jpg', 100),
                'vehicle_registration' => UploadedFile::fake()->create('orcr.jpg', 100),
            ],
        ];

        $response = $this->postJson('/api/logistics/rider/apply', $payload);
        $response->assertStatus(201);

        $application = RiderApplication::where('email', $email)->first();
        $this->assertNotNull($application);
        $this->assertSame('130000000', $application->region_code);
        $this->assertNull($application->province_code);
        $this->assertSame('City of Mandaluyong', $application->municipality);
        $this->assertSame('137401000', $application->municipality_code);
    }

    public function test_hub_transport_driver_application_persists_correctly(): void
    {
        $email = 'hub_driver@invoiz.test';
        $this->verifyRiderEmail($email);

        $payload = [
            'application_type' => 'hub_transport_driver',
            'name' => 'Pedro Penduko',
            'email' => $email,
            'phone' => '09201234567',
            'sex' => 'male',
            'birthday' => '1988-11-05',
            'accepted_terms' => '1',
            'house_number' => '88',
            'street' => 'MacArthur Highway',
            'address' => '88, MacArthur Highway, City of San Fernando, Pampanga, Central Luzon',
            'region' => 'Central Luzon',
            'region_code' => '030000000',
            'province' => 'Pampanga',
            'province_code' => '035400000',
            'municipality' => 'City of San Fernando',
            'municipality_code' => '035416000',
            'barangay' => 'Dolores',
            'barangay_code' => '035416008',
            'vehicle_type' => 'truck',
            'vehicle_name' => 'Isuzu Elf',
            'vehicle_model' => '2023 4-Wheeler',
            'parcel_capacity' => 200,
            'capacity_kg' => 2000,
            'license_plate' => 'TRK5678',
            'license_number' => 'N04-56-789012',
            'license_expiry' => date('Y-m-d', strtotime('+1 year')),
            'vehicle_registration' => 'VR567890',
            'documents' => [
                'valid_id' => UploadedFile::fake()->create('id.jpg', 100),
                'drivers_license' => UploadedFile::fake()->create('license.jpg', 100),
                'vehicle_registration' => UploadedFile::fake()->create('orcr.jpg', 100),
            ],
        ];

        $response = $this->postJson('/api/logistics/rider/apply', $payload);
        $response->assertStatus(201);

        $application = RiderApplication::where('email', $email)->first();
        $this->assertNotNull($application);
        $this->assertSame('hub_transport_driver', $application->application_type);
        $this->assertSame('Pampanga', $application->province);
        $this->assertSame('035400000', $application->province_code);
        $this->assertSame('City of San Fernando', $application->municipality);
    }
}
