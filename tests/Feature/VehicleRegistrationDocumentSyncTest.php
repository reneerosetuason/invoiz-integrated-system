<?php

namespace Tests\Feature;

use App\Models\LogisticsTruck;
use App\Models\RiderDocumentChangeRequest;
use App\Models\User;
use App\Services\RiderDocumentChangeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Approved vehicle-registration replacements stay in sync with the fleet
 * record of the driver who submitted them.
 *
 * The tables the service touches are created on the in-memory SQLite
 * connection only; no migrations run and no MySQL instance is used.
 */
class VehicleRegistrationDocumentSyncTest extends TestCase
{
    private RiderDocumentChangeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The self-contained schema is only created on the in-memory SQLite test connection.');
        }

        $this->service = app(RiderDocumentChangeService::class);
        Storage::fake('local');

        Schema::dropIfExists('riders');
        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('status')->default('available');
            $table->timestamps();
        });

        Schema::dropIfExists('rider_applications');
        Schema::create('rider_applications', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('application_type')->default('hub_transport_driver');
            $table->string('status')->default('approved');
            $table->unsignedBigInteger('hub_transport_driver_id')->nullable();
            $table->unsignedBigInteger('logistics_truck_id')->nullable();
            $table->string('license_plate')->nullable();
            $table->date('license_expiry')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('rider_application_documents');
        Schema::create('rider_application_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rider_application_id');
            $table->string('document_type');
            $table->string('original_filename')->nullable();
            $table->string('stored_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('rider_document_change_requests');
        Schema::create('rider_document_change_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rider_id');
            $table->unsignedBigInteger('rider_application_id');
            $table->string('document_type');
            $table->string('original_filename')->nullable();
            $table->string('stored_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->date('proposed_expiry')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('previous_document_id')->nullable();
            $table->string('previous_stored_path')->nullable();
            $table->string('previous_original_filename')->nullable();
            $table->string('previous_mime_type')->nullable();
            $table->unsignedBigInteger('previous_file_size')->nullable();
            $table->date('previous_expiry')->nullable();
            $table->unsignedBigInteger('approved_document_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('logistics_trucks');
        Schema::create('logistics_trucks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->string('ownership_type')->default('driver_owned');
            $table->unsignedBigInteger('hub_transport_driver_id')->nullable();
            $table->string('plate_number');
            $table->string('vehicle_type')->nullable();
            $table->string('model')->nullable();
            $table->integer('parcel_capacity')->nullable();
            $table->decimal('capacity_kg', 10, 2)->nullable();
            $table->string('status')->default('available');
            $table->string('verification_status')->default('approved');
            $table->string('vehicle_registration')->nullable();
            $table->string('document_path')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::dropIfExists('rider_notifications');
        Schema::create('rider_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rider_id');
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    private function admin(): User
    {
        $admin = new User([
            'name' => 'Global Logistics Admin',
            'email' => User::GLOBAL_LOGISTICS_ADMIN_EMAIL,
            'role' => 'logistics_admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'center_id' => null,
            'logistics_company_id' => null,
        ]);
        $admin->id = User::GLOBAL_LOGISTICS_ADMIN_ID;

        return $admin;
    }

    private function rider(int $id = 5): int
    {
        return DB::table('riders')->insertGetId([
            'id' => $id,
            'name' => 'Rider Five',
            'email' => 'rider-five@test.local',
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function application(array $overrides = []): int
    {
        return DB::table('rider_applications')->insertGetId(array_merge([
            'email' => 'rider-five@test.local',
            'application_type' => 'hub_transport_driver',
            'status' => 'approved',
            'hub_transport_driver_id' => 7,
            'logistics_truck_id' => null,
            'license_plate' => 'ABC-1234',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function officialRegistration(int $applicationId, string $path = 'rider-documents/100/reg-old.pdf'): int
    {
        Storage::disk('local')->put($path, '%PDF-1.4 old registration');

        return DB::table('rider_application_documents')->insertGetId([
            'rider_application_id' => $applicationId,
            'document_type' => 'vehicle_registration',
            'original_filename' => 'old-registration.pdf',
            'stored_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 28,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function changeRequest(int $riderId, int $applicationId, string $path = 'rider-documents/change-requests/5/reg-new.pdf'): int
    {
        Storage::disk('local')->put($path, '%PDF-1.4 new registration');

        return DB::table('rider_document_change_requests')->insertGetId([
            'rider_id' => $riderId,
            'rider_application_id' => $applicationId,
            'document_type' => 'vehicle_registration',
            'original_filename' => 'new-registration.pdf',
            'stored_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 28,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function truck(int $driverId, string $plate, string $documentPath): int
    {
        return DB::table('logistics_trucks')->insertGetId([
            'logistics_company_id' => 1,
            'ownership_type' => LogisticsTruck::OWNERSHIP_DRIVER,
            'hub_transport_driver_id' => $driverId,
            'plate_number' => $plate,
            'vehicle_type' => 'light_truck',
            'parcel_capacity' => 20,
            'capacity_kg' => 1000,
            'status' => LogisticsTruck::STATUS_AVAILABLE,
            'verification_status' => LogisticsTruck::VERIFICATION_APPROVED,
            'document_path' => $documentPath,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function documentPath(int $truckId): ?string
    {
        return DB::table('logistics_trucks')->where('id', $truckId)->value('document_path');
    }

    private function approve(int $changeRequestId): RiderDocumentChangeRequest
    {
        return $this->service->approve(
            RiderDocumentChangeRequest::query()->findOrFail($changeRequestId),
            $this->admin(),
        );
    }

    public function test_approved_registration_updates_the_matching_truck_only(): void
    {
        $this->rider();
        $applicationId = $this->application();
        $this->officialRegistration($applicationId);

        $matching = $this->truck(7, 'ABC-1234', 'trucks/old-matching.pdf');
        $otherOwnTruck = $this->truck(7, 'XYZ-9999', 'trucks/old-second.pdf');
        $otherDriverTruck = $this->truck(8, 'ABC-1234', 'trucks/other-driver.pdf');

        $changeRequestId = $this->changeRequest(5, $applicationId);

        $approved = $this->approve($changeRequestId);

        $this->assertSame('approved', $approved->status);
        $this->assertSame('rider-documents/change-requests/5/reg-new.pdf', $this->documentPath($matching));
        $this->assertSame('trucks/old-second.pdf', $this->documentPath($otherOwnTruck));
        $this->assertSame('trucks/other-driver.pdf', $this->documentPath($otherDriverTruck));
        $this->assertSame('rider-documents/100/reg-old.pdf', $approved->previous_stored_path);
        $this->assertSame(
            'rider-documents/change-requests/5/reg-new.pdf',
            DB::table('rider_application_documents')
                ->where('rider_application_id', $applicationId)
                ->where('document_type', 'vehicle_registration')
                ->value('stored_path'),
        );
    }

    public function test_application_truck_pointer_wins_when_the_plate_does_not_match(): void
    {
        $this->rider();
        $assigned = $this->truck(7, 'ASSIGNED-1', 'trucks/assigned-old.pdf');
        $plateTruck = $this->truck(7, 'ABC-1234', 'trucks/plate-old.pdf');

        $applicationId = $this->application(['logistics_truck_id' => $assigned]);
        $this->officialRegistration($applicationId);
        $changeRequestId = $this->changeRequest(5, $applicationId);

        $this->approve($changeRequestId);

        $this->assertSame('rider-documents/change-requests/5/reg-new.pdf', $this->documentPath($assigned));
        $this->assertSame('trucks/plate-old.pdf', $this->documentPath($plateTruck));
    }

    public function test_ambiguous_driver_vehicle_is_left_untouched(): void
    {
        $this->rider();
        $first = $this->truck(7, 'AAA-1111', 'trucks/first.pdf');
        $second = $this->truck(7, 'BBB-2222', 'trucks/second.pdf');

        $applicationId = $this->application(['license_plate' => 'UNKNOWN-0']);
        $this->officialRegistration($applicationId);
        $changeRequestId = $this->changeRequest(5, $applicationId);

        $this->approve($changeRequestId);

        $this->assertSame('trucks/first.pdf', $this->documentPath($first));
        $this->assertSame('trucks/second.pdf', $this->documentPath($second));
    }

    public function test_delivery_rider_application_without_a_driver_link_touches_no_truck(): void
    {
        $this->rider();
        $truck = $this->truck(8, 'ABC-1234', 'trucks/not-mine.pdf');

        $applicationId = $this->application([
            'application_type' => 'delivery_rider',
            'hub_transport_driver_id' => null,
        ]);
        $this->officialRegistration($applicationId);
        $changeRequestId = $this->changeRequest(5, $applicationId);

        $this->approve($changeRequestId);

        $this->assertSame('trucks/not-mine.pdf', $this->documentPath($truck));
    }

    public function test_rejection_does_not_modify_the_truck_or_the_official_document(): void
    {
        $this->rider();
        $applicationId = $this->application();
        $this->officialRegistration($applicationId);
        $truck = $this->truck(7, 'ABC-1234', 'trucks/old-matching.pdf');
        $changeRequestId = $this->changeRequest(5, $applicationId);

        $this->service->reject(
            RiderDocumentChangeRequest::query()->findOrFail($changeRequestId),
            $this->admin(),
            'The scan is unreadable; please upload a clearer copy.',
        );

        $this->assertSame('trucks/old-matching.pdf', $this->documentPath($truck));
        $this->assertSame(
            'rider-documents/100/reg-old.pdf',
            DB::table('rider_application_documents')
                ->where('rider_application_id', $applicationId)
                ->where('document_type', 'vehicle_registration')
                ->value('stored_path'),
        );
    }

    public function test_failed_approval_leaves_the_official_document_and_truck_unchanged(): void
    {
        $this->rider();
        $applicationId = $this->application();
        $this->officialRegistration($applicationId);
        $truck = $this->truck(7, 'ABC-1234', 'trucks/old-matching.pdf');
        $changeRequestId = $this->changeRequest(5, $applicationId);

        DB::table('rider_document_change_requests')
            ->where('id', $changeRequestId)
            ->update(['status' => 'approved']);

        try {
            $this->approve($changeRequestId);
            $this->fail('An already reviewed request must not be approved again.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame('trucks/old-matching.pdf', $this->documentPath($truck));
        $this->assertSame(
            'rider-documents/100/reg-old.pdf',
            DB::table('rider_application_documents')
                ->where('rider_application_id', $applicationId)
                ->where('document_type', 'vehicle_registration')
                ->value('stored_path'),
        );
    }
}
