<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Expired/invalid signed links for rider document change requests.
 *
 * These tests build only the single table the signed route binds, on the
 * in-memory SQLite connection; no migrations are executed and the MySQL
 * sandbox is never touched.
 */
class DocumentChangeSignedLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The self-contained schema is only created on the in-memory SQLite test connection.');
        }

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
    }

    private function validPdf(string $label = 'Document'): string
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

    private function changeRequest(string $storedPath = 'rider-documents/change-requests/1/proposed.pdf'): int
    {
        return DB::table('rider_document_change_requests')->insertGetId([
            'rider_id' => 1,
            'rider_application_id' => 1,
            'document_type' => 'drivers_license',
            'original_filename' => 'renewed-license.pdf',
            'stored_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'file_size' => 512,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function signedUrl(int $id, \DateTimeInterface $expiry, string $kind = 'proposed'): string
    {
        return URL::temporarySignedRoute(
            'api.logistics.rider.document-change-requests.document',
            $expiry,
            ['changeRequest' => $id, 'kind' => $kind],
        );
    }

    public function test_expired_document_change_link_returns_the_branded_403_page(): void
    {
        $id = $this->changeRequest();

        $response = $this->get($this->signedUrl($id, now()->subMinute()));

        $response->assertStatus(403);
        $response->assertSee('Document link expired');
    }

    public function test_expired_document_change_link_returns_a_json_403_without_leaking_the_path(): void
    {
        $id = $this->changeRequest('rider-documents/change-requests/1/private-file.pdf');

        $response = $this->getJson($this->signedUrl($id, now()->subMinute()));

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'This document link has expired or is invalid. Reopen the document from your authenticated account.',
        ]);
        $this->assertStringNotContainsString('rider-documents/', $response->getContent());
    }

    public function test_tampered_document_change_link_is_rejected(): void
    {
        $id = $this->changeRequest();

        $tampered = $this->signedUrl($id, now()->addMinutes(10)).'&tampered=1';

        $this->get($tampered)->assertStatus(403)->assertSee('Document link expired');
    }

    public function test_valid_signed_document_change_link_still_serves_the_file_privately(): void
    {
        Storage::fake('local');
        $path = 'rider-documents/change-requests/1/proposed.pdf';
        Storage::disk('local')->put($path, $this->validPdf('Renewed license'));

        $id = $this->changeRequest($path);

        $response = $this->get($this->signedUrl($id, now()->addMinutes(10)))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_other_signed_routes_keep_the_email_verification_behavior(): void
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => 1,
            'hash' => sha1('someone@test.local'),
        ]);

        $this->get($url)
            ->assertRedirect('/verify-email')
            ->assertSessionHas('error');
    }
}
