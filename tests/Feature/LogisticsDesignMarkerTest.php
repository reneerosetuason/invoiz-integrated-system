<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** TEMPORARY: asserts logistic-web design markers are the ones rendering. */
class LogisticsDesignMarkerTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('email', 'logistics_admin@test.com')->firstOrFail();
    }

    /** Markers that only exist in the logistic-web (new) design. */
    public function test_login_page_renders_the_new_design(): void
    {
        $body = $this->get('/logistics/login')->getContent();

        // new design: standalone document + 40/60 branded split + headline
        $this->assertStringContainsString('Keep every delivery moving.', $body, 'new login headline missing');
        $this->assertStringContainsString('Logistics Center', $body);
        $this->assertStringContainsString('cdn.tailwindcss.com', $body, 'new design uses the Tailwind CDN shell');
        // old design used the shared auth-split layout
        $this->assertStringNotContainsString('layouts.auth-split', $body, 'old auth-split layout still in use');

        // and it must post to the LOGISTICS login, not the marketplace one
        $this->assertStringContainsString('action="/logistics/login"', $body);
    }

    public function test_sidebar_renders_new_design_markers(): void
    {
        $body = $this->actingAs($this->admin())->get('/logistics/dashboard')->getContent();

        // new design: active-indicator bar + amber focus ring (from logistic-web)
        $this->assertStringContainsString('aria-current="page"]::before', $body, 'new active-indicator CSS missing');
        $this->assertStringContainsString('outline: 2px solid #F0A202', $body, 'new focus ring missing');
        // new sizing (70px rail / 248px expanded); the old fork used 72/256
        $this->assertStringContainsString('width: 70px', $body);
        $this->assertStringContainsString('width: 248px', $body);
        // sorting center nav restored from the new design
        $this->assertStringContainsString('/logistics/sorting-center', $body);
        // messages nav preserved from the fork
        $this->assertStringContainsString('/logistics/messages', $body);
    }

    public function test_center_application_status_renders_new_design(): void
    {
        $body = $this->get('/logistics/logistics-center/application-status')->getContent();
        $this->assertStringContainsString('cdn.tailwindcss.com', $body, 'new standalone design missing');
        $this->assertStringNotContainsString('layouts.auth-split', $body);
    }

    public function test_apply_form_uses_new_design_and_keeps_required_fields(): void
    {
        // the fork's CenterApplicationService requires SEC/TIN/region/postal;
        // the new design must not have dropped them, and must be the standalone look
        $body = $this->get('/logistics/logistics-center/apply')->getContent();

        $this->assertStringContainsString('cdn.tailwindcss.com', $body, 'apply page is not the new standalone design');
        $this->assertStringNotContainsString('layouts.auth-split', $body, 'apply page still uses the old layout');

        foreach (['sec_number', 'tin', 'postal_code', 'region', 'owner_first_name', 'owner_middle_name', 'owner_last_name', 'terms'] as $f) {
            $this->assertStringContainsString('name="'.$f.'"', $body, "apply form lost {$f}");
        }

        $this->assertStringNotContainsString('name="owner_name"', $body, 'legacy single owner_name field still present');
    }

    public function test_apply_submission_persists_end_to_end(): void
    {
        $email = 'e2e.center.'.uniqid().'@example.test';

        \Illuminate\Support\Facades\DB::table('rider_email_verifications')->insert([
            'email' => $email,
            'otp_hash' => bcrypt('123456'),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'consumed_at' => now(),
            'last_sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $province = \App\Models\Province::query()->whereNotNull('region_code')->first();
        $sec = 'AB'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        $tin = implode('-', str_split(str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT), 3));

        $payload = [
            'terms' => '1',
            'business_name' => 'E2E Logistics Hub '.uniqid(),
            'sec_number' => $sec,
            'tin' => $tin,
            'owner_first_name' => 'Juan',
            'owner_middle_name' => 'Santos',
            'owner_last_name' => 'Dela Cruz',
            'email' => $email,
            'phone' => '09171234567',
            'region' => $province?->region_code ?? '130000000',
            'province' => $province?->name ?? 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Barangay Uno',
            'house_number' => '42',
            'street' => 'Rizal Ave',
            'postal_code' => '1100',
            'documents' => [
                'valid_id' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
                'business_registration' => UploadedFile::fake()->create('br.pdf', 100, 'application/pdf'),
            ],
        ];

        $response = $this->post(route('center-application.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('logistics_center_applications', [
            'business_name' => $payload['business_name'],
            'sec_number' => $sec,
            'tin' => $tin,
            'owner_name' => 'Juan Santos Dela Cruz',
        ]);
    }

    public function test_apply_submission_rejected_without_terms(): void
    {
        $response = $this->post(route('center-application.store'), ['business_name' => 'No Terms Center']);

        $response->assertSessionHasErrors('terms');
        $this->assertDatabaseMissing('logistics_center_applications', ['business_name' => 'No Terms Center']);
    }
}
