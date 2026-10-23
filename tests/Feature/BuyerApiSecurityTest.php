<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BuyerApiSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function user(array $overrides = []): User
    {
        $this->seq++;

        return User::create(array_merge([
            'name' => 'Buyer API User '.$this->seq,
            'email' => 'buyer-api-'.$this->seq.'-'.Str::uuid().'@test.local',
            'password' => Hash::make('Password123!'),
            'role' => 'buyer',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'approval_status' => 'approved',
            'email_verified_at' => now(),
        ], $overrides));
    }

    public function test_active_buyer_can_use_protected_buyer_api_routes(): void
    {
        $buyer = $this->user();

        Sanctum::actingAs($buyer);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $buyer->id);

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonStructure(['cart']);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonStructure(['orders']);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $buyer->id);
    }

    public function test_existing_sanctum_token_cannot_access_buyer_api_after_suspension(): void
    {
        $buyer = $this->user();
        $token = $buyer->createToken('buyer-api-test')->plainTextToken;

        $buyer->update([
            'account_status' => 'suspended',
            'status' => 'suspended',
        ]);

        foreach (['/api/me', '/api/cart', '/api/orders', '/api/user'] as $uri) {
            $this->withToken($token)
                ->getJson($uri)
                ->assertForbidden()
                ->assertJsonPath('message', 'Your account is not active.');
        }
    }

    public function test_account_status_takes_precedence_for_buyer_api_access(): void
    {
        $buyer = $this->user();

        DB::table('users')->where('id', $buyer->id)->update([
            'account_status' => 'suspended',
            'status' => 'active',
        ]);

        $buyer->refresh();
        $this->assertFalse($buyer->isActive());

        Sanctum::actingAs($buyer);

        $this->getJson('/api/cart')->assertForbidden();
    }

    public function test_public_api_endpoints_remain_public(): void
    {
        $this->postJson('/api/login', [])->assertStatus(422);
    }

    public function test_buyer_address_ownership_remains_enforced(): void
    {
        $owner = $this->user();
        $otherBuyer = $this->user();

        $address = Address::create([
            'buyer_id' => $otherBuyer->id,
            'recipient_name' => 'Other Buyer',
            'phone' => '09171234567',
            'address_line' => 'Other Street',
            'barangay' => 'Other Barangay',
            'city' => 'Other City',
            'province' => 'Other Province',
            'is_default' => true,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/addresses/{$address->id}", [
            'address_line' => 'Changed Street',
        ])->assertNotFound();

        $this->assertSame('Other Street', $address->fresh()->address_line);
    }

    public function test_legacy_api_login_rejects_inactive_and_blocked_accounts(): void
    {
        $suspended = $this->user([
            'account_status' => 'suspended',
            'status' => 'suspended',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $suspended->email,
            'password' => 'Password123!',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Your account is not active.');

        $blocked = $this->user([
            'approval_status' => 'rejected',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $blocked->email,
            'password' => 'Password123!',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Your account is not approved.');

        $active = $this->user();

        $this->postJson('/api/auth/login', [
            'email' => $active->email,
            'password' => 'Password123!',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_private_buyer_api_routes_use_the_shared_active_account_middleware(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        foreach (['api/me', 'api/cart', 'api/orders', 'api/addresses', 'api/chat', 'api/user'] as $uri) {
            $route = $routes->first(
                fn ($route) => $route->uri() === $uri && in_array('GET', $route->methods(), true)
            );

            $this->assertNotNull($route, "Route [{$uri}] was not found.");
            $this->assertContains(
                'account.active',
                $route->gatherMiddleware(),
                "Route [{$uri}] is missing the active-account guard."
            );
        }

        $login = $routes->first(
            fn ($route) => $route->uri() === 'api/login' && in_array('POST', $route->methods(), true)
        );

        $this->assertNotNull($login);
        $this->assertNotContains('account.active', $login->gatherMiddleware());
    }
}
