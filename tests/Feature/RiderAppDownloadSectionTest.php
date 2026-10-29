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
            ->assertDontSee('Get the INVOIZ Rider App')
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

    public function test_installer_download_is_disabled_without_verified_configuration(): void
    {
        config([
            'services.rider_app.android_apk_url' => null,
            'services.rider_app.android_apk_verified' => false,
        ]);
        $this->get('/downloads/invoiz-rider.apk')->assertNotFound();
    }

    public function test_installer_download_rejects_untrusted_or_non_https_destinations(): void
    {
        foreach (['not-a-url', 'http://downloads.example.test/invoiz-rider.apk',
            'https://downloads.example.test/invoiz-rider.apk'] as $url) {
            config([
                'services.rider_app.android_apk_url' => $url,
                'services.rider_app.android_apk_verified' => true,
            ]);
            $this->get('/downloads/invoiz-rider.apk')->assertNotFound();
        }
    }
}
