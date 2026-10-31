<?php

namespace Tests\Feature;

use App\Models\LogisticsCenter;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private function user(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Admin Auth '.Str::random(8),
            'email' => 'admin-auth-'.Str::uuid().'@test.local',
            'password' => Hash::make('password123'),
            'role' => 'buyer',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function admin(array $overrides = []): User
    {
        return $this->user(array_merge([
            'role' => 'admin',
            'is_admin' => true,
        ], $overrides));
    }

    public function test_active_admin_can_access_protected_web_and_api_admin_routes(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/manage-accounts')
            ->assertOk();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/sellers')->assertOk();
    }

    public function test_guests_cannot_access_admin_pages_or_actions(): void
    {
        $target = $this->user(['role' => 'buyer']);

        $this->get('/admin/manage-accounts')->assertRedirect('/login');

        $this->post("/admin/manage-accounts/{$target->id}/action", ['action' => 'suspend'])
            ->assertRedirect('/login');

        $this->assertSame('active', $target->fresh()->account_status);

        $this->getJson('/api/admin/sellers')->assertUnauthorized();
    }

    public function test_non_admin_roles_are_forbidden_from_web_admin_routes(): void
    {
        foreach (['buyer', 'seller', 'rider', 'staff', 'logistics_staff'] as $role) {
            $user = $this->user(['role' => $role]);

            $this->actingAs($user)
                ->get('/admin/manage-accounts')
                ->assertForbidden();
        }

        $duplicate = $this->user(['role' => 'buyer']);
        DB::table('users')->where('id', $duplicate->id)->update(['role' => 'logistics_admin']);

        $this->actingAs($duplicate->fresh())
            ->get('/admin/manage-accounts')
            ->assertForbidden();
    }

    public function test_role_admin_without_is_admin_flag_is_forbidden_on_web_and_api(): void
    {
        $fakeAdmin = $this->user([
            'role' => 'admin',
            'is_admin' => false,
        ]);

        $this->actingAs($fakeAdmin)
            ->get('/admin/manage-accounts')
            ->assertForbidden();

        Sanctum::actingAs($fakeAdmin);

        $this->getJson('/api/admin/sellers')->assertForbidden();
    }

    public function test_suspended_and_inactive_admins_are_denied_and_signed_out(): void
    {
        foreach ([
            ['account_status' => 'suspended', 'status' => 'suspended'],
            ['account_status' => 'deactivated', 'status' => 'inactive'],
        ] as $state) {
            $admin = $this->admin($state);

            $this->actingAs($admin, 'web')
                ->get('/admin/manage-accounts')
                ->assertRedirect('/admin/login')
                ->assertSessionHasErrors('email');

            $this->assertGuest('web');

            Sanctum::actingAs($admin);

            $this->getJson('/api/admin/sellers')->assertForbidden();
        }
    }

    public function test_suspended_admin_cannot_establish_a_login_session(): void
    {
        foreach ([
            ['account_status' => 'suspended', 'status' => 'suspended'],
            ['account_status' => 'deactivated', 'status' => 'inactive'],
        ] as $state) {
            $admin = $this->admin(array_merge($state, [
                'password' => 'AdminPass123!',
            ]));

            $this->post('/admin/login', [
                'email' => $admin->email,
                'password' => 'AdminPass123!',
            ])->assertSessionHasErrors('email');

            $this->assertGuest('web');
        }
    }

    public function test_non_admins_cannot_invoke_representative_admin_mutations(): void
    {
        $buyer = $this->user(['role' => 'buyer']);
        $sellerUser = $this->user(['role' => 'seller']);
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'store_name' => 'Pending Authorization Store',
            'approval_status' => 'pending',
            'status' => 'active',
        ]);

        $this->actingAs($buyer)
            ->post("/admin/sellers/{$seller->id}/approve")
            ->assertForbidden();

        $this->assertSame('pending', $seller->fresh()->approval_status);

        $this->actingAs($buyer)
            ->post("/admin/manage-accounts/{$sellerUser->id}/action", ['action' => 'suspend'])
            ->assertForbidden();

        $this->assertSame('active', $sellerUser->fresh()->account_status);

        Sanctum::actingAs($buyer);

        $this->postJson("/api/admin/sellers/{$seller->id}/approve")
            ->assertForbidden();

        $this->assertSame('pending', $seller->fresh()->approval_status);
    }

    public function test_admin_login_page_is_public_and_valid_admin_can_login_and_logout(): void
    {
        $this->get('/admin/login')->assertOk();

        $admin = $this->admin(['password' => 'AdminPass123!']);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'AdminPass123!',
        ])->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($admin);

        $this->post('/admin/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_buyer_and_logistics_access_remain_available(): void
    {
        $buyer = $this->user(['role' => 'buyer']);

        $this->actingAs($buyer)
            ->get('/buyer/chat')
            ->assertOk();

        $center = LogisticsCenter::create([
            'name' => 'Authorization Test Center',
            'address' => '1 Authorization Street',
            'city' => 'Authorization City',
            'is_active' => true,
        ]);

        $staff = $this->user([
            'role' => 'logistics_staff',
            'center_id' => $center->id,
        ]);

        $this->actingAs($staff)
            ->get('/logistics/dashboard')
            ->assertOk();

        $rider = $this->user(['role' => 'rider']);
        Rider::create([
            'user_id' => $rider->id,
            'name' => $rider->name,
            'email' => $rider->email,
            'phone' => '09171234567',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'approved_at' => now(),
        ]);

        $this->actingAs($rider)
            ->get('/logistics/rider/entry')
            ->assertOk();
    }

    public function test_all_marketplace_admin_route_groups_use_the_authorization_middleware(): void
    {
        $adminRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'admin/'))
            ->reject(fn ($route) => in_array($route->getName(), [
                'admin.login',
                'admin.login.post',
                'admin.logout',
            ], true));

        $this->assertNotEmpty($adminRoutes);

        foreach ($adminRoutes as $route) {
            $this->assertContains(
                'marketplace.admin',
                $route->gatherMiddleware(),
                "Admin route [{$route->uri()}] is missing marketplace.admin middleware."
            );
        }

        $apiAdminRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/admin/'));

        $this->assertNotEmpty($apiAdminRoutes);

        foreach ($apiAdminRoutes as $route) {
            $this->assertContains(
                'marketplace.admin',
                $route->gatherMiddleware(),
                "API admin route [{$route->uri()}] is missing marketplace.admin middleware."
            );
        }
    }
}
