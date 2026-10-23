<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CenterApplicationConsentTest extends TestCase
{
    use DatabaseTransactions;

    private function verifyEmail(string $email, bool $expired = false): void
    {
        $createdAt = $expired ? now()->subHours(25) : now();

        DB::table('rider_email_verifications')->insert([
            'email' => $email,
            'otp_hash' => bcrypt('123456'),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'consumed_at' => now(),
            'last_sent_at' => now(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function payload(string $email, array $overrides = []): array
    {
        $sec = 'AB'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        $tin = implode('-', str_split(str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT), 3));

        return array_merge([
            'terms' => '1',
            'business_name' => 'Phase One Center '.Str::random(10),
            'sec_number' => $sec,
            'tin' => $tin,
            'owner_first_name' => 'Phase',
            'owner_middle_name' => 'One',
            'owner_last_name' => 'Owner',
            'email' => $email,
            'phone' => '09'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'region' => '130000000',
            'municipality' => 'Quezon City',
            'barangay' => 'Barangay One',
            'house_number' => '42',
            'street' => 'Rizal Avenue',
            'postal_code' => '1100',
            'documents' => [
                'valid_id' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
                'business_registration' => UploadedFile::fake()->create('registration.pdf', 100, 'application/pdf'),
            ],
        ], $overrides);
    }

    public function test_api_requires_terms_consent(): void
    {
        $email = 'phase-one-terms-'.uniqid().'@test.local';
        $this->verifyEmail($email);

        $payload = $this->payload($email);
        unset($payload['terms']);

        $this->postJson('/api/logistics/center/apply', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('terms');

        $this->assertDatabaseMissing('logistics_center_applications', [
            'business_name' => $payload['business_name'],
        ]);
    }

    public function test_api_requires_a_verified_email(): void
    {
        $email = 'phase-one-unverified-'.uniqid().'@test.local';
        $payload = $this->payload($email);

        $this->postJson('/api/logistics/center/apply', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseMissing('logistics_center_applications', [
            'business_name' => $payload['business_name'],
        ]);
    }

    public function test_api_rejects_an_expired_verification(): void
    {
        $email = 'phase-one-expired-'.uniqid().'@test.local';
        $this->verifyEmail($email, true);
        $payload = $this->payload($email);

        $this->postJson('/api/logistics/center/apply', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseMissing('logistics_center_applications', [
            'business_name' => $payload['business_name'],
        ]);
    }

    public function test_api_accepts_a_verified_email_with_consent(): void
    {
        $email = 'phase-one-verified-'.uniqid().'@test.local';
        $this->verifyEmail($email);
        $payload = $this->payload($email);

        $this->postJson('/api/logistics/center/apply', $payload)
            ->assertCreated()
            ->assertJsonPath('application.submitted_via', 'mobile');

        $this->assertDatabaseHas('logistics_center_applications', [
            'business_name' => $payload['business_name'],
            'email' => $email,
            'submitted_via' => 'mobile',
        ]);
    }

    public function test_web_flow_accepts_a_verified_email_with_consent(): void
    {
        $email = 'phase-one-web-'.uniqid().'@test.local';
        $this->verifyEmail($email);
        $payload = $this->payload($email);

        $this->post(route('center-application.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('logistics_center_applications', [
            'business_name' => $payload['business_name'],
            'email' => $email,
            'submitted_via' => 'web',
        ]);
    }
}
