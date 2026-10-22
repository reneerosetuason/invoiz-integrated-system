<?php

namespace Tests\Feature;

use App\Models\LogisticsCenter;
use App\Models\RiderApplication;
use App\Models\RiderApplicationDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RiderDocumentServingTest extends TestCase
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
            'email' => "docs-{$role}-{$this->seq}@test.local",
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
}
