<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * TEMPORARY render smoke test used to verify the logistic-web view design was
 * applied without breaking any logistics page. Not intended to be committed.
 */
class LogisticsRenderSmokeTest extends TestCase
{
    use DatabaseTransactions;

    private function pages(): array
    {
        return [
            '/logistics/dashboard',
            '/logistics/deliveries',
            '/logistics/deliveries/scan',
            '/logistics/deliveries-archived',
            '/logistics/riders',
            '/logistics/pickup-requests',
            '/logistics/staff',
            '/logistics/rider-applications',
            '/logistics/center-applications',
            '/logistics/centers',
            '/logistics/service-areas',
            '/logistics/transactions',
            '/logistics/remittances',
            '/logistics/reports',
            '/logistics/messages',
            '/logistics/notifications',
            '/logistics/settings',
            '/logistics/profile',
            '/logistics/sorting-center',
        ];
    }

    private function admin(): User
    {
        $u = User::where('email', 'logistics_admin@test.com')->first();
        if (! $u) {
            $this->markTestSkipped('logistics_admin@test.com not seeded');
        }

        return $u;
    }

    public function test_every_logistics_page_renders_for_logistics_admin(): void
    {
        $admin = $this->admin();

        foreach ($this->pages() as $uri) {
            $response = $this->actingAs($admin)->get($uri);

            $body = $response->getContent();

            $this->assertSame(200, $response->status(), "{$uri} returned {$response->status()}");
            $this->assertGreaterThan(
                500,
                strlen($body),
                "{$uri} rendered an empty page"
            );
            $this->assertStringNotContainsString('Whoops', $body, "{$uri} threw an exception");
            $this->assertStringNotContainsString('Undefined variable', $body, "{$uri} has an undefined variable");
        }
    }

    public function test_public_center_application_pages_render(): void
    {
        foreach (['/logistics/logistics-center/apply', '/logistics/logistics-center/application-status', '/logistics/login'] as $uri) {
            $response = $this->get($uri);
            $this->assertSame(200, $response->status(), "{$uri} returned {$response->status()}");
            $this->assertStringNotContainsString('Whoops', $response->getContent(), "{$uri} threw");
        }
    }

    public function test_sidebar_contains_logistics_messages_and_admin_sections(): void
    {
        $admin = $this->admin();
        $body = $this->actingAs($admin)->get('/logistics/dashboard')->getContent();

        // nav entry re-added during the design swap
        $this->assertStringContainsString('/logistics/messages', $body, 'messages nav link missing from sidebar');
        // management section must render for logistics_admin
        $this->assertStringContainsString('/logistics/staff', $body, 'management section hidden for logistics_admin');
        // account menu (source design) must render
        $this->assertStringContainsString('accountOpen', $body, 'account menu state missing');
    }

    public function test_staff_cannot_reach_admin_only_pages(): void
    {
        $staff = User::where('email', 'logistics_staff@test.com')->first();
        if (! $staff) {
            $this->markTestSkipped('logistics_staff@test.com not seeded');
        }

        // dashboard is staff-allowed
        $this->actingAs($staff)->get('/logistics/dashboard')->assertOk();

        // rider applications are admin-only
        $this->actingAs($staff)->get('/logistics/rider-applications')->assertForbidden();
    }

    public function test_remittances_view_is_read_only_for_staff(): void
    {
        $staff = User::where('email', 'logistics_staff@test.com')->first();
        if (! $staff) {
            $this->markTestSkipped('logistics_staff@test.com not seeded');
        }

        // Staff may view the COD handoff list for their center...
        $this->actingAs($staff)->get('/logistics/remittances')->assertOk();

        // ...but there is deliberately no staff confirm action: the seller is
        // the confirming party (see App\Support\Remittances).
        $this->assertFalse(Route::has('remittances.confirm'));
    }
}
