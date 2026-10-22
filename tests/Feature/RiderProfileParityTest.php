<?php

namespace Tests\Feature;

use App\Models\LogisticsCenter;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\RiderApplicationDocument;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Rider profile parity coverage: the license-status flags, the online
 * eligibility gate, and the signed private-document endpoint that the merged
 * rider profile screen relies on. Uses DatabaseTransactions; every row is
 * rolled back. Protected by Tests\TestCase::guardAgainstDestructiveDatabase().
 */
class RiderProfileParityTest extends TestCase
{
    use DatabaseTransactions;

    private function riderUser(array $riderExtra = []): User
    {
        $email = 'rider.'.bin2hex(random_bytes(3)).'@test.local';

        $user = User::create([
            'name' => 'Test Rider',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => 'active',
        ]);

        Rider::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Test Rider',
            'email' => $email,
            'phone' => '09171234567',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
        ], $riderExtra));

        return $user->fresh();
    }

    private function center(): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => 'Parity Center',
            'address' => '1 Warehouse Rd',
            'city' => 'Quezon City',
            'is_active' => true,
        ]);
    }

    private function area(LogisticsCenter $center): ServiceArea
    {
        return ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => 'Area A',
            'is_active' => true,
        ]);
    }

    // ------------------------------------------------ license status flags

    public function test_profile_reports_a_valid_license_and_clears_the_online_gate(): void
    {
        $user = $this->riderUser(['license_expiry' => now()->addYear()->format('Y-m-d')]);

        Sanctum::actingAs($user);

        $this->getJson('/api/logistics/rider/profile')
            ->assertOk()
            ->assertJsonPath('rider.license_status', 'valid')
            ->assertJsonPath('rider.license_expired', false)
            ->assertJsonPath('rider.license_expiring_soon', false)
            ->assertJsonPath('rider.can_go_online', true)
            ->assertJsonPath('rider.online_block_reason', null);
    }

    public function test_profile_reports_an_expired_license_and_blocks_online(): void
    {
        $user = $this->riderUser(['license_expiry' => now()->subDay()->format('Y-m-d')]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/logistics/rider/profile')
            ->assertOk()
            ->assertJsonPath('rider.license_status', 'expired')
            ->assertJsonPath('rider.license_expired', true)
            ->assertJsonPath('rider.can_go_online', false);

        $this->assertStringContainsString('expired', (string) $response->json('rider.online_block_reason'));
    }

    public function test_profile_flags_a_license_expiring_within_thirty_days(): void
    {
        $user = $this->riderUser(['license_expiry' => now()->addDays(10)->format('Y-m-d')]);

        Sanctum::actingAs($user);

        $this->getJson('/api/logistics/rider/profile')
            ->assertOk()
            ->assertJsonPath('rider.license_status', 'expiring')
            ->assertJsonPath('rider.license_expired', false)
            ->assertJsonPath('rider.license_expiring_soon', true)
            ->assertJsonPath('rider.can_go_online', true);
    }

    public function test_profile_reports_a_missing_license_without_blocking_online(): void
    {
        // Legacy riders predate the licence fields: a null expiry must not
        // block an otherwise-approved rider.
        $user = $this->riderUser(['license_expiry' => null]);

        Sanctum::actingAs($user);

        $this->getJson('/api/logistics/rider/profile')
            ->assertOk()
            ->assertJsonPath('rider.license_status', 'missing')
            ->assertJsonPath('rider.license_expired', false)
            ->assertJsonPath('rider.can_go_online', true);
    }

    public function test_profile_blocks_online_for_an_unverified_vehicle(): void
    {
        $user = $this->riderUser([
            'vehicle_verification' => 'pending',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/logistics/rider/profile')
            ->assertOk()
            ->assertJsonPath('rider.can_go_online', false)
            ->assertJsonPath('rider.online_block_reason', 'Vehicle not verified');
    }

    // ------------------------------------------------ server-side online gate

    public function test_going_online_is_blocked_when_the_license_is_expired(): void
    {
        $user = $this->riderUser(['license_expiry' => now()->subDay()->format('Y-m-d')]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/logistics/rider/status', ['status' => 'online'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertFalse((bool) Rider::where('user_id', $user->id)->value('is_online'));
    }

    public function test_an_eligible_rider_can_go_online(): void
    {
        $user = $this->riderUser(['license_expiry' => now()->addYear()->format('Y-m-d')]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/logistics/rider/status', ['status' => 'online'])
            ->assertOk()
            ->assertJsonPath('status', 'online');

        $this->assertTrue((bool) Rider::where('user_id', $user->id)->value('is_online'));
    }

    // ------------------------------------------------ signed documents
    private function validPdf(): string
    {
        $content = 'BT /F1 12 Tf 72 720 Td (Rider ID) Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $number = $index + 1;
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $size = count($objects) + 1;

        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";

        for ($number = 1; $number < $size; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        return $pdf."trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
    }

    public function test_profile_lists_documents_and_the_signed_url_serves_them(): void
    {
        Storage::fake('local');

        $center = $this->center();
        $area = $this->area($center);
        $user = $this->riderUser([
            'center_id' => $center->id,
            'service_area_id' => $area->id,
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ]);

        $application = RiderApplication::create([
            'name' => 'Test Rider',
            'email' => $user->email,
            'phone' => '09171234567',
            'vehicle_type' => 'motorcycle',
            'status' => 'approved',
        ]);

        $pdf = $this->validPdf();
        Storage::disk('local')->put('rider-documents/test.pdf', $pdf);

        $document = RiderApplicationDocument::create([
            'rider_application_id' => $application->id,
            'document_type' => 'valid_id',
            'original_filename' => 'my-id.pdf',
            'stored_path' => 'rider-documents/test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdf),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/logistics/rider/profile')
            ->assertOk()
            ->assertJsonPath('rider.documents.0.id', $document->id)
            ->assertJsonPath('rider.documents.0.type', 'valid_id')
            ->assertJsonPath('rider.documents.0.label', 'Valid ID')
            ->assertJsonPath('rider.documents.0.name', 'my-id.pdf');

        $signedUrl = $response->json('rider.documents.0.url');
        $this->assertStringContainsString('signature=', (string) $signedUrl);

        $this->get($signedUrl)->assertOk();
    }

    public function test_unsigned_document_request_is_rejected(): void
    {
        Storage::fake('local');

        $user = $this->riderUser(['license_expiry' => now()->addYear()->format('Y-m-d')]);

        $application = RiderApplication::create([
            'name' => 'Test Rider',
            'email' => $user->email,
            'phone' => '09171234567',
            'vehicle_type' => 'motorcycle',
            'status' => 'approved',
        ]);

        Storage::disk('local')->put('rider-documents/test.pdf', 'hello world');

        $document = RiderApplicationDocument::create([
            'rider_application_id' => $application->id,
            'document_type' => 'valid_id',
            'original_filename' => 'my-id.pdf',
            'stored_path' => 'rider-documents/test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 11,
        ]);

        $this->get('/api/logistics/rider/documents/'.$document->id)->assertForbidden();
    }

    public function test_document_of_an_unprovisioned_application_is_forbidden(): void
    {
        Storage::fake('local');

        // Application email has no matching Rider — the signature is valid but
        // the document must not be exposed.
        $application = RiderApplication::create([
            'name' => 'Ghost',
            'email' => 'ghost.'.bin2hex(random_bytes(3)).'@test.local',
            'phone' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'status' => 'pending',
        ]);

        Storage::disk('local')->put('rider-documents/ghost.pdf', 'secret');

        $document = RiderApplicationDocument::create([
            'rider_application_id' => $application->id,
            'document_type' => 'valid_id',
            'original_filename' => 'ghost.pdf',
            'stored_path' => 'rider-documents/ghost.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 6,
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'api.logistics.rider.documents.show',
            now()->addMinutes(30),
            ['document' => $document->id],
        );

        $this->get($signedUrl)->assertForbidden();
    }
}
