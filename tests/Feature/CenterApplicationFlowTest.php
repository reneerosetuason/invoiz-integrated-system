<?php

namespace Tests\Feature;

use App\Mail\CenterEmailVerificationMail;
use App\Models\LogisticsCenterApplication;
use App\Models\RiderEmailVerification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CenterApplicationDatabase;
use Tests\TestCase;

class CenterApplicationFlowTest extends TestCase
{
    use CenterApplicationDatabase;

    private const EMAIL = 'center@example.test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCenterApplicationDatabase();
    }

    private function code(array $attributes = []): RiderEmailVerification
    {
        return RiderEmailVerification::create(array_merge([
            'email' => self::EMAIL, 'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5), 'last_sent_at' => now()->subMinutes(2), 'attempts' => 0,
        ], $attributes));
    }

    private function payload(): array
    {
        return [
            'business_name' => 'Central Parcel Hub', 'owner_first_name' => 'Maria', 'owner_last_name' => 'Santos',
            'email' => self::EMAIL, 'phone' => '+639171234567', 'sec_number' => 'AB123456789',
            'tin' => '123-456-789-000', 'region' => '130000000', 'postal_code' => '1100', 'terms' => '1',
            'documents' => [
                'valid_id' => UploadedFile::fake()->create('id.pdf', 10, 'application/pdf'),
                'business_registration' => UploadedFile::fake()->create('business.pdf', 10, 'application/pdf'),
            ],
        ];
    }

    public function test_form_and_status_pages_render_with_configured_regions_without_external_lookup(): void
    {
        $this->get(route('center-application.apply'))->assertOk()->assertSee('130000000')->assertSee('Submit Application');
        $this->get(route('center-application.status'))->assertOk()->assertSee('data-send-code', false);
    }

    public function test_code_delivery_is_hashed_private_and_rate_limited(): void
    {
        $this->postJson(route('center-application.verification.send'), ['email' => self::EMAIL])->assertOk()->assertJsonMissingPath('code');
        $record = RiderEmailVerification::firstOrFail();
        Mail::assertSent(CenterEmailVerificationMail::class, fn ($mail) => $mail->hasTo(self::EMAIL) && Hash::check($mail->code, $record->otp_hash));
        $this->postJson(route('center-application.verification.send'), ['email' => self::EMAIL])->assertStatus(429);
        Mail::assertSentCount(1);
    }

    public function test_code_is_email_bound_expiring_and_single_use(): void
    {
        $record = $this->code();
        $this->postJson(route('center-application.verification.verify'), ['email' => 'other@example.test', 'code' => '123456'])->assertStatus(422);
        $this->postJson(route('center-application.verification.verify'), ['email' => self::EMAIL, 'code' => '654321'])->assertStatus(422);
        $this->assertSame(1, $record->fresh()->attempts);
        $this->postJson(route('center-application.verification.verify'), ['email' => self::EMAIL, 'code' => '123456'])->assertOk()->assertJsonPath('email', self::EMAIL)->assertSessionHas('application_status_access.email', self::EMAIL);
        $this->postJson(route('center-application.verification.verify'), ['email' => self::EMAIL, 'code' => '123456'])->assertStatus(422);
        $this->code(['expires_at' => now()->subSecond()]);
        $this->postJson(route('center-application.verification.verify'), ['email' => self::EMAIL, 'code' => '123456'])->assertStatus(422)->assertJsonPath('message', 'Verification code expired. Please request a new code.');
    }

    public function test_resending_replaces_previous_code_and_attempt_limit_blocks_verification(): void
    {
        $old = $this->code();
        $this->postJson(route('center-application.verification.send'), ['email' => self::EMAIL])->assertOk();
        $this->assertDatabaseMissing('rider_email_verifications', ['id' => $old->id]);
        RiderEmailVerification::firstOrFail()->update(['attempts' => RiderEmailVerification::MAX_ATTEMPTS]);
        $this->postJson(route('center-application.verification.verify'), ['email' => self::EMAIL, 'code' => '123456'])->assertStatus(429);
    }

    public function test_mail_failure_does_not_leave_an_unusable_code(): void
    {
        Mail::shouldReceive('to')->once()->with(self::EMAIL)->andThrow(new \RuntimeException('Simulated mail failure'));
        $this->postJson(route('center-application.verification.send'), ['email' => self::EMAIL])->assertStatus(500);
        $this->assertDatabaseCount('rider_email_verifications', 0);
    }

    public function test_marketplace_email_cannot_be_used_for_an_application(): void
    {
        DB::table('users')->insert(['email' => self::EMAIL, 'role' => 'buyer']);
        $this->postJson(route('center-application.verification.send'), ['email' => self::EMAIL])->assertStatus(422)->assertJsonValidationErrors('email');
        Mail::assertNothingSent();
    }

    public function test_verified_submission_stores_documents_notifies_admin_and_can_be_tracked(): void
    {
        $this->code();
        $this->postJson(route('center-application.verification.verify'), ['email' => self::EMAIL, 'code' => '123456'])->assertOk();
        $this->post(route('center-application.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect(route('center-application.apply'))->assertSessionHas('success');
        $application = LogisticsCenterApplication::firstOrFail();
        $this->assertSame('pending', $application->status);
        $this->assertSame('09171234567', $application->phone);
        $this->assertSame('Maria Santos', $application->owner_name);
        $this->assertCount(2, $application->supportingDocuments);
        foreach ($application->supportingDocuments as $document) Storage::assertExists($document->stored_path);
        $this->assertDatabaseHas('notifications', ['type' => 'new_center_application']);
        foreach (['pending', 'approved', 'rejected'] as $status) {
            $application->update(['status' => $status]);
            $this->post(route('center-application.status.check'), ['email' => self::EMAIL])->assertOk()->assertSee('Central Parcel Hub')->assertSee(ucfirst($status));
        }
    }

    public function test_invalid_location_documents_and_missing_consent_do_not_create_application(): void
    {
        $this->code(['consumed_at' => now()]);
        $payload = $this->payload();
        $payload['region'] = '';
        $payload['postal_code'] = '123';
        $payload['terms'] = '0';
        $payload['documents']['valid_id'] = UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream');
        $payload['documents']['business_registration'] = UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf');
        $this->postJson(route('center-application.store'), $payload)->assertStatus(422)->assertJsonValidationErrors(['region', 'postal_code', 'terms', 'documents.valid_id', 'documents.business_registration']);
        $this->assertDatabaseCount('logistics_center_applications', 0);
    }

    public function test_duplicate_pending_application_is_rejected_but_rejected_application_can_reapply(): void
    {
        $this->code(['consumed_at' => now()]);
        $this->post(route('center-application.store'), $this->payload())->assertSessionHasNoErrors();
        $this->postJson(route('center-application.store'), $this->payload())->assertStatus(422)->assertJsonValidationErrors(['email', 'business_name', 'phone', 'sec_number', 'tin']);
        LogisticsCenterApplication::firstOrFail()->update(['status' => 'rejected']);
        $this->postJson(route('center-application.store'), $this->payload())->assertRedirect();
        $this->assertDatabaseCount('logistics_center_applications', 2);
    }

    public function test_status_requires_verification_and_reports_missing_application(): void
    {
        $this->postJson(route('center-application.status.check'), ['email' => self::EMAIL])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->code();
        $this->postJson(route('center-application.verification.verify'), ['email' => self::EMAIL, 'code' => '123456'])->assertOk();
        $this->post(route('center-application.status.check'), ['email' => self::EMAIL])->assertOk()->assertSee('No application found');
    }

    public function test_local_address_fallback_routes_return_cascading_options(): void
    {
        $province = DB::table('provinces')->insertGetId(['code' => '030100000', 'name' => 'Local Province', 'region_code' => '030000000']);
        $municipality = DB::table('municipalities')->insertGetId(['code' => '030101000', 'name' => 'Local Municipality', 'province_id' => $province]);
        DB::table('barangays')->insert(['code' => '030101001', 'name' => 'Local Barangay', 'municipality_id' => $municipality]);
        $this->getJson("/api/logistics/address/provinces/{$province}/municipalities")->assertOk()->assertJsonPath('municipalities.0.name', 'Local Municipality');
        $this->getJson("/api/logistics/address/municipalities/{$municipality}/barangays")->assertOk()->assertJsonPath('barangays.0.name', 'Local Barangay');
    }
}
