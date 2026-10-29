<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RiderAppDownloadSectionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_root_route_loads_the_buyer_landing_with_seller_and_rider_sections(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Sell on INVOIZ')
            ->assertSee('Ride with us')
            ->assertSee('Open logistics')
            ->assertSee('Get the INVOIZ Rider App')
            ->assertSee('Download for Android')
            ->assertSee('Login')
            ->assertSee('Sign Up');
    }

    public function test_logistics_entry_still_sends_guests_to_the_dedicated_login_url(): void
    {
        $this->get('/logistics')->assertRedirect('/logistics/login');
    }

    public function test_logistics_login_page_and_staff_post_login_redirect_remain_role_specific(): void
    {
        $this->get('/logistics/login')
            ->assertOk()
            ->assertSee('Keep every delivery moving.')
            ->assertSee('action="/logistics/login"', false);

        $staff = User::create([
            'name' => 'Landing Regression Logistics Staff',
            'email' => 'landing-logistics-'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'logistics_staff',
            'status' => 'active',
            'account_status' => 'active',
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);

        $this->post('/logistics/login', [
            'email' => $staff->email,
            'password' => 'secret123',
        ])->assertRedirect('/logistics/dashboard');

        $this->assertAuthenticatedAs($staff);
    }

    private function landingHtml(): string
    {
        return view('buyer.landing', [
            'deals' => [],
            'categories' => collect(),
            'stores' => collect(),
            'stats' => ['products' => 0, 'sellers' => 0, 'orders' => 0],
            'favoriteIds' => [],
            'sort' => 'recommended',
        ])->render();
    }

    public function test_rider_download_is_truthfully_unavailable_without_a_configured_apk(): void
    {
        config([
            'services.rider_app.android_apk_url' => null,
            'services.rider_app.android_apk_verified' => false,
        ]);

        $html = $this->landingHtml();

        $this->assertStringContainsString('Get the INVOIZ Rider App', $html);
        $this->assertStringContainsString('The Android installer is not yet available.', $html);
        $this->assertStringContainsString('disabled aria-describedby="rider-app-download-status"', $html);
        $this->assertStringContainsString('Android installation steps may vary by device and Android version.', $html);
        $this->assertStringNotContainsString('id="rider-app-download-link"', $html);
        $this->assertStringNotContainsString('role="img" aria-label="QR code linking to the INVOIZ Rider App Android download"', $html);
        $this->assertStringContainsString('href="'.url('/login').'"', $html);
        $this->assertStringContainsString('href="'.url('/register').'"', $html);
    }

    public function test_rider_download_stays_unavailable_until_a_hosted_installer_is_verified(): void
    {
        config([
            'services.rider_app.android_apk_url' => 'https://downloads.example.test/invoiz-rider.apk',
            'services.rider_app.android_apk_verified' => true,
        ]);

        $html = $this->landingHtml();

        $this->assertStringContainsString('The Android installer is not yet available.', $html);
        $this->assertStringNotContainsString('https://downloads.example.test/invoiz-rider.apk', $html);
        $this->assertStringNotContainsString('id="rider-app-download-link"', $html);
        $this->assertStringNotContainsString('role="img" aria-label="QR code linking to the INVOIZ Rider App Android download"', $html);
    }

    public function test_invalid_or_non_https_rider_destination_keeps_download_disabled(): void
    {
        foreach (['not-a-url', 'http://downloads.example.test/invoiz-rider.apk'] as $url) {
            config([
                'services.rider_app.android_apk_url' => $url,
                'services.rider_app.android_apk_verified' => true,
            ]);

            $html = $this->landingHtml();

            $this->assertStringContainsString('The Android installer is not yet available.', $html);
            $this->assertStringNotContainsString('id="rider-app-download-link"', $html);
        }
    }
}
