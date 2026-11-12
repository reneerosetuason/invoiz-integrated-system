<?php

namespace Tests\Feature;

use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\RiderApplicationDocument;
use App\Models\RiderDocumentChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Rider document change requests: rider submission, admin review, license
 * eligibility and document-access security.
 *
 * Every official document row is only promoted by an authorized Logistics
 * Admin approval; a rider upload never writes the official record directly.
 */
class RiderDocumentChangeRequestTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    // ---------------------------------------------------------------- helpers

    private function validPdf(string $label = 'Rider document'): string
    {
        $content = "BT /F1 12 Tf 72 720 Td ({$label}) Tj ET";
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

        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function admin(): User
    {
        $admin = User::whereKey(User::GLOBAL_LOGISTICS_ADMIN_ID)->first()
            ?? User::where('email', User::GLOBAL_LOGISTICS_ADMIN_EMAIL)->first();

        if ($admin) {
            return $admin;
        }

        return User::create([
            'name' => 'Logistics Admin',
            'phone' => fake()->unique()->numerify('09#########'),
            'email' => User::GLOBAL_LOGISTICS_ADMIN_EMAIL,
            'password' => Hash::make('secret123'),
            'role' => 'logistics_admin',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function staff(): User
    {
        $this->seq++;

        return User::create([
            'name' => 'Logistics Staff '.$this->seq,
            'phone' => fake()->unique()->numerify('09#########'),
            'email' => "docs-staff-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => 'logistics_staff',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function marketplaceAdmin(): User
    {
        $this->seq++;

        return User::create([
            'name' => 'Marketplace Admin '.$this->seq,
            'phone' => fake()->unique()->numerify('09#########'),
            'email' => "docs-marketplace-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @return array{0: User, 1: Rider, 2: RiderApplication, 3: RiderApplicationDocument}
     */
    private function riderWithLicense(string $licenseExpiry, string $riderStatus = 'available'): array
    {
        $this->seq++;
        $email = "docs-rider-{$this->seq}@test.local";

        $user = User::create([
            'name' => 'Document Rider '.$this->seq,
            'phone' => fake()->unique()->numerify('09#########'),
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $rider = Rider::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $email,
            'phone' => '091700000'.$this->seq,
            'vehicle_type' => 'motorcycle',
            'license_number' => 'N01-23-456789',
            'license_expiry' => $licenseExpiry,
            'status' => $riderStatus,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
        ]);

        $application = RiderApplication::create([
            'name' => $user->name,
            'email' => $email,
            'phone' => $rider->phone,
            'vehicle_type' => 'motorcycle',
            'license_number' => 'N01-23-456789',
            'license_expiry' => $licenseExpiry,
            'status' => 'approved',
            'application_type' => 'delivery_rider',
        ]);

        $path = "rider-documents/{$application->id}/original-license.pdf";
        Storage::disk('local')->put($path, $this->validPdf('Original license'));

        $document = RiderApplicationDocument::create([
            'rider_application_id' => $application->id,
            'document_type' => 'drivers_license',
            'original_filename' => 'original-license.pdf',
            'stored_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => strlen($this->validPdf('Original license')),
        ]);

        return [$user->fresh(), $rider->fresh(), $application->fresh(), $document->fresh()];
    }

    private function riderPdf(string $filename = 'renewed-license.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($filename, $this->validPdf('Renewed license'));
    }

    private function submit(User $riderUser, array $overrides = [], ?UploadedFile $file = null)
    {
        Sanctum::actingAs($riderUser, ['rider']);

        return $this->postJson('/api/logistics/rider/document-change-requests', array_merge([
            'document_type' => 'drivers_license',
            'document' => $file ?? $this->riderPdf(),
            'expiry_date' => now()->addYears(2)->format('Y-m-d'),
            'reason' => 'License renewed at the LTO branch.',
        ], $overrides));
    }

    // --------------------------------------------------------- rider submits

    public function test_rider_can_submit_a_license_replacement_and_it_stays_pending(): void
    {
        Storage::fake('local');
        [$user, $rider, , $official] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));

        $this->submit($user)->assertStatus(201)->assertJsonPath('request.status', 'pending');

        $request = RiderDocumentChangeRequest::where('rider_id', $rider->id)->firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame('drivers_license', $request->document_type);
        $this->assertNotNull($request->stored_path);
        Storage::disk('local')->assertExists($request->stored_path);

        // The official record and eligibility are untouched while pending.
        $this->assertSame($official->stored_path, $official->fresh()->stored_path);
        $this->assertSame('original-license.pdf', $official->fresh()->original_filename);
        $this->assertSame(
            now()->subMonth()->format('Y-m-d'),
            $rider->fresh()->license_expiry?->format('Y-m-d'),
        );
    }

    public function test_pending_request_does_not_restore_an_expired_license(): void
    {
        Storage::fake('local');
        [$user, $rider] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));

        $this->assertFalse($rider->canGoOnline());
        $this->assertStringContainsString('expired', strtolower((string) $rider->assignmentBlockReason()));

        $this->submit($user)->assertStatus(201);

        $this->assertFalse($rider->fresh()->canGoOnline(), 'A pending request must not restore eligibility.');
    }

    public function test_rider_cannot_replace_the_official_document_outside_the_review_flow(): void
    {
        Storage::fake('local');
        [$user, , , $official] = $this->riderWithLicense(now()->addYear()->format('Y-m-d'));

        $this->submit($user)->assertStatus(201);

        // No rider-facing route updates the official document row; it is only
        // written by the approval flow.
        $this->assertSame($official->stored_path, $official->fresh()->stored_path);
        $this->assertSame($official->document_type, $official->fresh()->document_type);
    }

    public function test_duplicate_pending_requests_for_the_same_document_type_are_blocked(): void
    {
        Storage::fake('local');
        [$user, $rider] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));

        $this->submit($user)->assertStatus(201);

        Sanctum::actingAs($user, ['rider']);
        $this->postJson('/api/logistics/rider/document-change-requests', [
            'document_type' => 'drivers_license',
            'document' => $this->riderPdf('second.pdf'),
            'expiry_date' => now()->addYears(2)->format('Y-m-d'),
        ])->assertStatus(422)->assertJsonValidationErrors('document_type');

        $this->assertSame(
            1,
            RiderDocumentChangeRequest::where('rider_id', $rider->id)->pending()->count(),
        );
    }

    public function test_replacement_file_type_and_size_are_validated(): void
    {
        Storage::fake('local');
        [$user] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));

        $this->submit($user, [], UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('document');

        $this->submit($user, [], UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('document');
    }

    public function test_license_replacement_requires_a_future_expiry(): void
    {
        Storage::fake('local');
        [$user] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));

        $this->submit($user, ['expiry_date' => now()->subDay()->format('Y-m-d')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('expiry_date');

        $this->submit($user, ['expiry_date' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('expiry_date');
    }

    // ------------------------------------------------------- review console

    public function test_pending_request_is_visible_in_the_logistics_review_list(): void
    {
        Storage::fake('local');
        [$user] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));
        $this->submit($user)->assertStatus(201);

        $this->actingAs($this->admin())
            ->get('/logistics/document-change-requests')
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('Pending Approval');
    }

    public function test_admin_approval_promotes_the_file_and_updates_license_expiry(): void
    {
        Storage::fake('local');
        [$user, $rider, $application, $official] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));

        $this->submit($user)->assertStatus(201);
        $request = RiderDocumentChangeRequest::where('rider_id', $rider->id)->firstOrFail();
        $newExpiry = now()->addYears(2)->format('Y-m-d');

        $this->actingAs($this->admin())
            ->post("/logistics/document-change-requests/{$request->id}/approve")
            ->assertRedirect("/logistics/document-change-requests/{$request->id}");

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertNotNull($request->reviewed_at);
        $this->assertNotNull($request->reviewed_by);

        $official->refresh();
        $this->assertSame($request->stored_path, $official->stored_path);
        $this->assertSame($request->original_filename, $official->original_filename);

        $this->assertSame($newExpiry, $rider->fresh()->license_expiry?->format('Y-m-d'));
        $this->assertSame($newExpiry, $application->fresh()->license_expiry?->format('Y-m-d'));
        $this->assertTrue($rider->fresh()->canGoOnline(), 'Approved valid license restores eligibility.');
    }

    public function test_approval_preserves_the_previous_document_and_history(): void
    {
        Storage::fake('local');
        [$user, $rider, , $official] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));

        $this->submit($user)->assertStatus(201);
        $request = RiderDocumentChangeRequest::where('rider_id', $rider->id)->firstOrFail();
        $oldPath = $official->stored_path;

        $this->actingAs($this->admin())
            ->post("/logistics/document-change-requests/{$request->id}/approve");

        $request->refresh();
        $this->assertSame($official->id, $request->previous_document_id);
        $this->assertSame($oldPath, $request->previous_stored_path);
        $this->assertSame('original-license.pdf', $request->previous_original_filename);
        $this->assertSame(
            now()->subMonth()->format('Y-m-d'),
            $request->previous_expiry?->format('Y-m-d'),
        );

        Storage::disk('local')->assertExists($oldPath);
        $this->assertNotNull($request->previousDocumentModel());
        $this->assertTrue($request->previousDocumentModel()->isServable());

        $this->actingAs($this->admin())
            ->get("/logistics/document-change-requests/{$request->id}")
            ->assertOk()
            ->assertSee('original-license.pdf');
    }

    public function test_rejection_requires_a_reason_and_leaves_the_official_record_unchanged(): void
    {
        Storage::fake('local');
        [$user, $rider, $application, $official] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));

        $this->submit($user)->assertStatus(201);
        $request = RiderDocumentChangeRequest::where('rider_id', $rider->id)->firstOrFail();

        $this->actingAs($this->admin())
            ->post("/logistics/document-change-requests/{$request->id}/reject", ['reason' => 'no'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('pending', $request->fresh()->status);

        $this->actingAs($this->admin())
            ->post("/logistics/document-change-requests/{$request->id}/reject", [
                'reason' => 'The uploaded photo is blurry and unreadable.',
            ])
            ->assertRedirect("/logistics/document-change-requests/{$request->id}");

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertSame('The uploaded photo is blurry and unreadable.', $request->rejection_reason);
        $this->assertNotNull($request->reviewed_at);
        $this->assertNotNull($request->reviewed_by);

        $this->assertSame($official->stored_path, $official->fresh()->stored_path);
        $this->assertSame(
            now()->subMonth()->format('Y-m-d'),
            $rider->fresh()->license_expiry?->format('Y-m-d'),
        );
        $this->assertSame(
            now()->subMonth()->format('Y-m-d'),
            $application->fresh()->license_expiry?->format('Y-m-d'),
        );
    }

    public function test_rider_sees_the_rejection_reason_and_can_resubmit(): void
    {
        Storage::fake('local');
        [$user, $rider] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));

        $this->submit($user)->assertStatus(201);
        $request = RiderDocumentChangeRequest::where('rider_id', $rider->id)->firstOrFail();

        $this->actingAs($this->admin())
            ->post("/logistics/document-change-requests/{$request->id}/reject", [
                'reason' => 'Expiry date does not match the license photo.',
            ]);

        Sanctum::actingAs($user, ['rider']);
        $overview = $this->getJson('/api/logistics/rider/document-change-requests')
            ->assertOk()
            ->assertJsonPath('requests.0.status', 'rejected')
            ->assertJsonPath('requests.0.rejection_reason', 'Expiry date does not match the license photo.')
            ->assertJsonPath('requests.0.can_resubmit', true)
            ->json();

        $this->assertNotEmpty($overview['requests'][0]['document']['url']);

        $this->postJson('/api/logistics/rider/document-change-requests', [
            'document_type' => 'drivers_license',
            'document' => $this->riderPdf('corrected-license.pdf'),
            'expiry_date' => now()->addYears(3)->format('Y-m-d'),
            'reason' => 'Corrected expiry and clearer photo.',
        ])->assertStatus(201);

        $this->assertSame(
            1,
            RiderDocumentChangeRequest::where('rider_id', $rider->id)->pending()->count(),
        );
    }

    public function test_processing_a_request_twice_is_rejected_and_changes_nothing(): void
    {
        Storage::fake('local');
        [$user, $rider, , $official] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));

        $this->submit($user)->assertStatus(201);
        $request = RiderDocumentChangeRequest::where('rider_id', $rider->id)->firstOrFail();

        $admin = $this->admin();
        $this->actingAs($admin)
            ->post("/logistics/document-change-requests/{$request->id}/reject", [
                'reason' => 'Document does not match the rider on file.',
            ]);

        $before = $official->fresh()->only(['stored_path', 'original_filename', 'mime_type', 'file_size']);

        $this->actingAs($admin)
            ->post("/logistics/document-change-requests/{$request->id}/approve")
            ->assertSessionHasErrors('status');

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame($before, $official->fresh()->only(['stored_path', 'original_filename', 'mime_type', 'file_size']));
    }

    // ------------------------------------------------------ authorization

    public function test_logistics_staff_cannot_review_or_read_change_request_documents(): void
    {
        Storage::fake('local');
        [$user, $rider] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));
        $this->submit($user)->assertStatus(201);
        $request = RiderDocumentChangeRequest::where('rider_id', $rider->id)->firstOrFail();

        $staff = $this->staff();

        $this->actingAs($staff)
            ->get('/logistics/document-change-requests')
            ->assertForbidden();

        $this->actingAs($staff)
            ->get("/logistics/document-change-requests/{$request->id}")
            ->assertForbidden();

        $this->actingAs($staff)
            ->get("/logistics/document-change-requests/{$request->id}/documents/proposed/view")
            ->assertForbidden();

        $this->actingAs($staff)
            ->post("/logistics/document-change-requests/{$request->id}/approve")
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_marketplace_admin_cannot_review_rider_document_requests(): void
    {
        Storage::fake('local');
        [$user, $rider] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));
        $this->submit($user)->assertStatus(201);
        $request = RiderDocumentChangeRequest::where('rider_id', $rider->id)->firstOrFail();

        $this->actingAs($this->marketplaceAdmin())
            ->get('/logistics/document-change-requests')
            ->assertForbidden();

        $this->actingAs($this->marketplaceAdmin())
            ->post("/logistics/document-change-requests/{$request->id}/approve")
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_riders_only_see_their_own_requests(): void
    {
        Storage::fake('local');
        [$firstUser, $firstRider] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));
        [$secondUser] = $this->riderWithLicense(now()->addYear()->format('Y-m-d'));

        $this->submit($firstUser)->assertStatus(201);
        $request = RiderDocumentChangeRequest::where('rider_id', $firstRider->id)->firstOrFail();

        Sanctum::actingAs($secondUser, ['rider']);
        $this->getJson('/api/logistics/rider/document-change-requests')
            ->assertOk()
            ->assertJsonCount(0, 'requests');

        $this->getJson("/api/logistics/rider/document-change-requests/{$request->id}/document/proposed")
            ->assertForbidden();
    }

    public function test_document_preview_requires_a_valid_signed_url_and_is_private(): void
    {
        Storage::fake('local');
        [$user, $rider] = $this->riderWithLicense(now()->subMonth()->format('Y-m-d'));
        $this->submit($user)->assertStatus(201);
        $request = RiderDocumentChangeRequest::where('rider_id', $rider->id)->firstOrFail();

        $this->getJson("/api/logistics/rider/document-change-requests/{$request->id}/document/proposed")
            ->assertForbidden();

        $signed = URL::temporarySignedRoute(
            'api.logistics.rider.document-change-requests.document',
            now()->addMinutes(10),
            ['changeRequest' => $request->id, 'kind' => 'proposed'],
        );

        $response = $this->get($signed)->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        $previous = URL::temporarySignedRoute(
            'api.logistics.rider.document-change-requests.document',
            now()->addMinutes(10),
            ['changeRequest' => $request->id, 'kind' => 'previous'],
        );

        // Nothing has been approved yet, so there is no previous file.
        $this->get($previous)->assertNotFound();
    }

    public function test_non_license_documents_do_not_require_an_expiry(): void
    {
        Storage::fake('local');
        [$user, $rider] = $this->riderWithLicense(now()->addYear()->format('Y-m-d'));

        $this->submit($user, [
            'document_type' => 'valid_id',
            'expiry_date' => null,
        ], $this->riderPdf('valid-id.pdf'))->assertStatus(201);

        $request = RiderDocumentChangeRequest::where('rider_id', $rider->id)->firstOrFail();
        $this->assertSame('valid_id', $request->document_type);
        $this->assertNull($request->proposed_expiry);
        $this->assertSame(
            now()->addYear()->format('Y-m-d'),
            $rider->fresh()->license_expiry?->format('Y-m-d'),
            'Non-license approvals must not touch the license expiry.',
        );
    }
}
