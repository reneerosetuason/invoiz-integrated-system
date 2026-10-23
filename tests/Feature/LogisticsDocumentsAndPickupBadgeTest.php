<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\RiderApplication;
use App\Models\RiderApplicationDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Rider application document serving (BUG-01) and the sidebar pickup badge
 * (BUG-02). All records are created inside the test transaction; storage uses
 * the fake local disk, so no real document files are touched.
 */
class LogisticsDocumentsAndPickupBadgeTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function adminUser(): User
    {
        return $this->user('logistics_admin');
    }

    private function user(string $role, ?int $centerId = null): User
    {
        $this->seq++;

        return User::create([
            'name' => ucfirst($role).' '.$this->seq,
            'email' => "docs-badge-{$role}-{$this->seq}@test.local",
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
        return LogisticsCenter::create([
            'name' => $name,
            'address' => '1 Test Street',
            'city' => 'Test City',
            'is_active' => true,
        ]);
    }

    private function delivery(?LogisticsCenter $center = null, array $overrides = []): Delivery
    {
        $this->seq++;

        return Delivery::create(array_merge([
            'tracking_number' => 'TRK-20261020-8'.str_pad((string) $this->seq, 3, '0', STR_PAD_LEFT),
            'order_id' => Order::query()->value('id'),
            'sender_name' => 'Test Sender',
            'recipient_name' => 'Test Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'center_id' => $center?->id,
            'status' => 'assigned',
            'parcel_status' => 'received',
        ], $overrides));
    }

    private function pickupRequest(Delivery $delivery, LogisticsCenter $center, string $status = 'pending'): PickupRequest
    {
        return PickupRequest::create([
            'delivery_id' => $delivery->id,
            'center_id' => $center->id,
            'status' => $status,
            'requested_at' => now(),
        ]);
    }

    private function application(): RiderApplication
    {
        $this->seq++;

        return RiderApplication::create([
            'name' => 'Document Applicant '.$this->seq,
            'email' => "document-applicant-{$this->seq}@test.local",
            'phone' => '09170000000',
            'vehicle_type' => 'Motorcycle',
            'status' => 'pending',
        ]);
    }

    private function documentFile(
        RiderApplication $application,
        string $contents,
        string $mime = 'application/pdf',
        bool $write = true,
        string $filename = 'valid-id.pdf',
    ): RiderApplicationDocument {
        $path = 'rider-documents/'.$application->id.'/'.uniqid('doc-', true).'.pdf';

        if ($write) {
            Storage::disk('local')->put($path, $contents);
        }

        return RiderApplicationDocument::create([
            'rider_application_id' => $application->id,
            'document_type' => 'valid_id',
            'original_filename' => $filename,
            'stored_path' => $path,
            'mime_type' => $mime,
            'file_size' => strlen($contents),
        ]);
    }

    private function validPdf(string $label = 'Valid ID'): string
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

    private function servedBytes(TestResponse $response): string
    {
        return (string) file_get_contents($response->baseResponse->getFile()->getPathname());
    }

    // ---------------------------------------------------------------- BUG-01

    public function test_admin_can_view_a_valid_pdf_inline_with_correct_headers(): void
    {
        Storage::fake('local');

        $admin = $this->adminUser();
        $pdf = $this->validPdf();
        $document = $this->documentFile($this->application(), $pdf);

        $response = $this->actingAs($admin)
            ->get("/logistics/rider-applications/documents/{$document->id}/view")
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertSame($pdf, $this->servedBytes($response));
    }

    public function test_admin_can_download_the_same_document_and_bytes_match(): void
    {
        Storage::fake('local');

        $admin = $this->adminUser();
        $pdf = $this->validPdf('Downloadable ID');
        $document = $this->documentFile($this->application(), $pdf);

        $view = $this->actingAs($admin)
            ->get("/logistics/rider-applications/documents/{$document->id}/view")
            ->assertOk();

        $download = $this->actingAs($admin)
            ->get("/logistics/rider-applications/documents/{$document->id}/download")
            ->assertOk();

        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
        $this->assertSame($pdf, $this->servedBytes($view));
        $this->assertSame($pdf, $this->servedBytes($download));
    }

    public function test_corrupt_pdf_is_reported_instead_of_served_to_the_viewer(): void
    {
        Storage::fake('local');

        $admin = $this->adminUser();
        // Same placeholder the old seeder wrote: a header but no pages/xref.
        $document = $this->documentFile(
            $this->application(),
            "%PDF-1.4\n% Demo rider document: authorization_letter\n",
        );

        $this->actingAs($admin)
            ->get("/logistics/rider-applications/documents/{$document->id}/view")
            ->assertStatus(422);
    }

    public function test_missing_and_empty_files_return_clear_errors(): void
    {
        Storage::fake('local');

        $admin = $this->adminUser();

        $missing = $this->documentFile($this->application(), 'unused', write: false);
        $empty = $this->documentFile($this->application(), '');

        $this->actingAs($admin)
            ->get("/logistics/rider-applications/documents/{$missing->id}/view")
            ->assertNotFound();

        $this->actingAs($admin)
            ->get("/logistics/rider-applications/documents/{$empty->id}/view")
            ->assertStatus(422);
    }

    public function test_unauthorized_users_cannot_access_documents(): void
    {
        Storage::fake('local');

        $center = $this->center('Document Center');
        $staff = $this->user('logistics_staff', $center->id);
        $rider = $this->user('rider');
        $document = $this->documentFile($this->application(), $this->validPdf());

        $this->actingAs($staff)
            ->get("/logistics/rider-applications/documents/{$document->id}/view")
            ->assertForbidden();

        $this->actingAs($staff)
            ->get("/logistics/rider-applications/documents/{$document->id}/download")
            ->assertForbidden();

        $this->actingAs($rider)
            ->get("/logistics/rider-applications/documents/{$document->id}/view")
            ->assertRedirect('/logistics/login');
    }

    public function test_document_route_serves_the_requested_document_only(): void
    {
        Storage::fake('local');

        $admin = $this->adminUser();
        $application = $this->application();
        $first = $this->documentFile($application, $this->validPdf('First'));
        $second = $this->documentFile($application, $this->validPdf('Second'));

        $firstBytes = $this->servedBytes(
            $this->actingAs($admin)->get("/logistics/rider-applications/documents/{$first->id}/view")->assertOk()
        );

        $secondBytes = $this->servedBytes(
            $this->actingAs($admin)->get("/logistics/rider-applications/documents/{$second->id}/view")->assertOk()
        );

        $this->assertNotSame($firstBytes, $secondBytes);
    }

    // ---------------------------------------------------------------- BUG-02

    public function test_pickup_badge_counts_pending_requests_and_clears_after_review(): void
    {
        $admin = $this->adminUser();
        $center = $this->center('Badge Center A');
        $first = $this->pickupRequest($this->delivery($center), $center);
        $second = $this->pickupRequest($this->delivery($center), $center);

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests')
            ->assertOk()
            ->assertSee('data-pickup-count="2"', false);

        // Opening a request must not complete it or change the badge.
        $this->actingAs($admin)
            ->get("/logistics/pickup-requests/{$first->id}")
            ->assertOk();

        $this->assertSame('pending', $first->fresh()->status);
        $this->actingAs($admin)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="2"', false);

        $this->actingAs($admin)
            ->post("/logistics/pickup-requests/{$first->id}/approve")
            ->assertRedirect();

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="1"', false);

        $this->actingAs($admin)
            ->post("/logistics/pickup-requests/{$second->id}/reject", ['rejection_reason' => 'Pickup slot unavailable.'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="0"', false);

        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('rejected', $second->fresh()->status);
    }

    public function test_pickup_badge_is_scoped_to_the_user_and_center(): void
    {
        $centerA = $this->center('Badge Center A');
        $centerB = $this->center('Badge Center B');

        $this->pickupRequest($this->delivery($centerA), $centerA);
        $this->pickupRequest($this->delivery($centerB), $centerB);

        $admin = $this->adminUser();
        $staffA = $this->user('logistics_staff', $centerA->id);
        $staffB = $this->user('logistics_staff', $centerB->id);
        $unassigned = $this->user('logistics_staff');

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="2"', false);

        $this->actingAs($staffA)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="1"', false)
            ->assertDontSee('Badge Center B');

        $this->actingAs($staffB)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="1"', false)
            ->assertDontSee('Badge Center A');

        $this->actingAs($unassigned)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="0"', false);
    }

    public function test_pending_request_already_picked_up_is_not_counted(): void
    {
        $admin = $this->adminUser();
        $center = $this->center('Stale Badge Center');
        $delivery = $this->delivery($center, [
            'status' => 'out_for_delivery',
            'picked_up_at' => now(),
        ]);

        $stale = $this->pickupRequest($delivery, $center, 'pending');

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="0"', false);

        // The historical row is preserved; only the actionable count ignores it.
        $this->assertSame('pending', $stale->fresh()->status);
    }

    public function test_pickup_request_completes_when_delivery_reaches_pickup(): void
    {
        $center = $this->center('Lifecycle Center');
        $delivery = $this->delivery($center, ['status' => 'going_to_pickup']);
        $pickup = $this->pickupRequest($delivery, $center, 'approved');

        $delivery->status = 'picked_up';
        $delivery->picked_up_at = now();
        $delivery->save();

        $fresh = $pickup->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->reviewed_at);
    }

    public function test_pickup_request_completes_when_saved_directly_past_pickup(): void
    {
        $center = $this->center('Direct Save Center');
        $delivery = $this->delivery($center, ['status' => 'going_to_pickup']);
        $pickup = $this->pickupRequest($delivery, $center, 'pending');

        $delivery->status = 'out_for_delivery';
        $delivery->picked_up_at = now();
        $delivery->save();

        $this->assertSame('completed', $pickup->fresh()->status);
    }
}
