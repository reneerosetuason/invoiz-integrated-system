<?php

namespace Tests\Feature;

use App\Mail\RiderPasswordResetMail;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderAccountStatusTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function user(string $role, array $overrides = []): User
    {
        $this->seq++;

        return User::create(array_merge([
            'name' => ucfirst($role).' '.$this->seq,
            'email' => "rider-status-{$role}-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => $role,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function rider(User $user, array $overrides = []): Rider
    {
        return Rider::create(array_merge([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '09171234567',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'is_verified' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ], $overrides));
    }

    private function login(User $user)
    {
        return $this->postJson('/api/logistics/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
    }

    public function test_linked_buyer_with_an_approved_rider_profile_can_use_web_and_api(): void
    {
        $buyer = $this->user('buyer');
        $rider = $this->rider($buyer);

        $this->actingAs($buyer)
            ->get('/logistics/rider/entry')
            ->assertOk();

        $response = $this->login($buyer)
            ->assertOk()
            ->assertJsonPath('user.role', 'buyer')
            ->assertJsonPath('user.rider.id', $rider->id);

        $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
            ->getJson('/api/logistics/rider/profile')
            ->assertOk()
            ->assertJsonPath('rider.id', $rider->id);
    }

    public function test_buyer_without_a_rider_profile_is_rejected_by_api_and_web(): void
    {
        $buyer = $this->user('buyer');

        $this->login($buyer)->assertStatus(403);

        $this->actingAs($buyer)
            ->get('/logistics/rider/entry')
            ->assertRedirect('/logistics/login');
    }

    public function test_unapproved_linked_rider_is_rejected_by_api_and_web(): void
    {
        $buyer = $this->user('buyer');
        $this->rider($buyer, ['approved_at' => null]);

        $this->login($buyer)->assertStatus(403);

        $this->actingAs($buyer)
            ->get('/logistics/rider/entry')
            ->assertRedirect('/logistics/login');
    }

    public function test_inactive_rider_profile_is_rejected_by_api_and_web(): void
    {
        $buyer = $this->user('buyer');
        $this->rider($buyer, ['status' => 'inactive']);

        $this->login($buyer)->assertStatus(403);

        $this->actingAs($buyer)
            ->get('/logistics/rider/entry')
            ->assertRedirect('/logistics/login');
    }

    public function test_suspended_account_status_revokes_existing_rider_access(): void
    {
        $user = $this->user('rider');
        $this->rider($user);

        $user->forceFill(['account_status' => 'suspended'])->save();
        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/logistics/rider/profile')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Your account has been suspended. Please contact an administrator.');

        $this->actingAs($user->fresh())
            ->get('/logistics/rider/entry')
            ->assertRedirect('/logistics/login');
    }

    public function test_inactive_account_status_revokes_existing_rider_access(): void
    {
        $user = $this->user('rider', ['account_status' => 'deactivated']);
        $this->rider($user);

        $this->assertSame('deactivated', $user->fresh()->account_status);
        $this->assertFalse($user->fresh()->isActive());

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/logistics/rider/profile')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Your account is currently inactive. Please contact an administrator.');
    }

    public function test_active_account_status_overrides_a_legacy_suspended_status(): void
    {
        $buyer = $this->user('buyer', [
            'status' => 'suspended',
            'account_status' => 'active',
        ]);
        $this->rider($buyer);

        $this->login($buyer)->assertOk();

        $this->actingAs($buyer)
            ->get('/logistics/rider/entry')
            ->assertOk();
    }

    public function test_suspended_account_status_is_rejected_even_when_legacy_status_is_active(): void
    {
        $buyer = $this->user('buyer', [
            'status' => 'active',
            'account_status' => 'suspended',
        ]);
        $this->rider($buyer);

        $this->login($buyer)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Your account is not active. Please contact support.');
    }

    public function test_password_reset_is_not_sent_for_a_suspended_account(): void
    {
        Mail::fake();

        $user = $this->user('rider', ['account_status' => 'suspended']);
        $this->rider($user);

        $this->postJson('/api/logistics/rider/password/forgot', [
            'email' => $user->email,
        ])->assertOk();

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_password_reset_is_sent_for_an_active_rider(): void
    {
        Mail::fake();

        $user = $this->user('rider');
        $this->rider($user);

        $this->postJson('/api/logistics/rider/password/forgot', [
            'email' => $user->email,
        ])->assertOk();

        Mail::assertSent(RiderPasswordResetMail::class);
    }
}
