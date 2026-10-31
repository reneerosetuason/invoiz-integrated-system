<?php

namespace Tests\Feature;

use App\Models\LogisticsCenter;
use App\Models\LogisticsCenterApplication;
use App\Models\LogisticsCompany;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LogisticsAccessAlignmentTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function user(string $role, array $overrides = []): User
    {
        $this->seq++;

        return User::create(array_merge([
            'name' => ucfirst($role).' '.$this->seq,
            'email' => "phase12-{$role}-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => $role,
            'status' => 'active',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function globalAdmin(): User
    {
        if ($existing = User::where('email', User::GLOBAL_LOGISTICS_ADMIN_EMAIL)->first()) {
            return $existing;
        }

        $user = new User([
            'name' => 'INVOIZ Global Logistics Admin',
            'email' => User::GLOBAL_LOGISTICS_ADMIN_EMAIL,
            'password' => Hash::make('secret123'),
            'role' => 'logistics_admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->id = User::GLOBAL_LOGISTICS_ADMIN_ID;
        $user->save();

        return $user;
    }

    private function center(string $name = 'Phase 12 Center'): LogisticsCenter
    {
        $this->seq++;

        return LogisticsCenter::create([
            'name' => $name.' '.$this->seq,
            'address' => '1 Phase 12 Street',
            'city' => 'Test City',
            'is_active' => true,
        ]);
    }

    private function application(User $user, array $overrides = []): LogisticsCenterApplication
    {
        $this->seq++;

        return LogisticsCenterApplication::create(array_merge([
            'business_name' => 'Phase 12 Logistics '.$this->seq,
            'owner_name' => $user->name,
            'email' => $user->email,
            'phone' => '0917'.random_int(1000000, 9999999),
            'address' => '1 Phase 12 Street',
            'status' => 'pending',
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

    /**
     * @return array{0: User, 1: int}
     */
    private function provisionCenterStaff(string $role): array
    {
        Mail::fake();

        $admin = $this->globalAdmin();
        $user = $this->user($role);
        $application = $this->application($user);
        $company = LogisticsCompany::create([
            'name' => 'Verified Phase 12 Company '.$this->seq,
            'registration_number' => 'PH12-'.$this->seq,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post("/logistics/center-applications/{$application->id}/approve", [
                'logistics_company_id' => $company->id,
                'ownership_evidence' => 'Verified registration record PH12-'.$this->seq,
            ])
            ->assertRedirect();

        $user = $user->fresh();
        $application = $application->fresh();

        $this->assertSame($role, $user->role);
        $this->assertNotNull($user->center_id);
        $this->assertSame('approved', $application->status);
        $this->assertNotNull($application->provisioned_at);

        return [$user, (int) $user->center_id];
    }

    public function test_provisioned_buyer_staff_can_enter_logistics_and_is_center_scoped(): void
    {
        [$buyer, $centerId] = $this->provisionCenterStaff('buyer');
        $center = LogisticsCenter::findOrFail($centerId);
        $otherCenter = $this->center('Other Phase 12 Center');
        $ownRider = $this->rider($this->user('rider'), ['center_id' => $center->id]);
        $otherRider = $this->rider($this->user('rider'), ['center_id' => $otherCenter->id]);

        $this->actingAs($buyer)
            ->get('/logistics')
            ->assertRedirect('/logistics/dashboard');

        $this->actingAs($buyer)
            ->get('/logistics/dashboard')
            ->assertOk();

        $this->actingAs($buyer)
            ->get("/logistics/riders/{$ownRider->id}")
            ->assertOk();

        $this->actingAs($buyer)
            ->get("/logistics/riders/{$otherRider->id}")
            ->assertForbidden();
    }

    public function test_provisioned_seller_staff_can_enter_logistics(): void
    {
        [$seller] = $this->provisionCenterStaff('seller');

        $this->actingAs($seller)
            ->get('/logistics/dashboard')
            ->assertOk();
    }

    public function test_incidental_center_id_does_not_grant_logistics_access(): void
    {
        $center = $this->center();

        foreach (['buyer', 'seller'] as $role) {
            $user = $this->user($role, ['center_id' => $center->id]);

            $this->actingAs($user)
                ->get('/logistics/dashboard')
                ->assertRedirect('/logistics/login')
                ->assertSessionHas('error');

            $this->assertGuest();
        }
    }

    public function test_incidental_center_id_does_not_pass_logistics_login(): void
    {
        $center = $this->center();
        $buyer = $this->user('buyer', ['center_id' => $center->id]);

        $this->post('/logistics/login', [
            'email' => $buyer->email,
            'password' => 'secret123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_approved_application_without_provisioning_marker_does_not_grant_access(): void
    {
        $center = $this->center();
        $buyer = $this->user('buyer', ['center_id' => $center->id]);
        $this->application($buyer, [
            'status' => 'approved',
            'provisioned_at' => null,
        ]);

        $this->actingAs($buyer)
            ->get('/logistics/dashboard')
            ->assertRedirect('/logistics/login')
            ->assertSessionHas('error');
    }

    public function test_linked_buyer_rider_can_use_web_and_api_with_approved_profile(): void
    {
        $buyer = $this->user('buyer');
        $rider = $this->rider($buyer);

        $this->actingAs($buyer)
            ->get('/logistics/rider/entry')
            ->assertOk();

        $response = $this->postJson('/api/logistics/login', [
            'email' => $buyer->email,
            'password' => 'secret123',
        ])->assertOk()
            ->assertJsonPath('user.role', 'buyer')
            ->assertJsonPath('user.rider.id', $rider->id)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'role',
                    'rider' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'vehicle_type',
                        'license_plate',
                        'license_number',
                        'license_expiry',
                        'license_status',
                        'license_expired',
                        'license_expiring_soon',
                        'vehicle_registration',
                        'company',
                        'service_area',
                        'status',
                        'is_online',
                        'can_go_online',
                        'online_block_reason',
                        'avatar',
                    ],
                ],
            ]);

        $token = $response->json('token');

        $this->assertNotEmpty($token);
        $this->assertSame('buyer', $buyer->fresh()->role);
        $this->assertTrue($buyer->tokens()->where('name', 'rider-app')->exists());
        $this->assertContains('rider', $buyer->tokens()->first()->abilities);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/logistics/rider/profile')
            ->assertOk()
            ->assertJsonPath('rider.id', $rider->id);
    }

    public function test_linked_seller_rider_can_authenticate_to_the_rider_api(): void
    {
        $seller = $this->user('seller');
        $this->rider($seller);

        $this->postJson('/api/logistics/login', [
            'email' => $seller->email,
            'password' => 'secret123',
        ])->assertOk()
            ->assertJsonPath('user.role', 'seller');
    }

    public function test_buyer_without_a_rider_profile_cannot_authenticate_as_a_rider(): void
    {
        $buyer = $this->user('buyer');

        $this->postJson('/api/logistics/login', [
            'email' => $buyer->email,
            'password' => 'secret123',
        ])->assertStatus(403);

        $this->assertSame(0, $buyer->tokens()->count());
    }

    public function test_unapproved_linked_rider_is_rejected_by_api_and_web(): void
    {
        $buyer = $this->user('buyer');
        $this->rider($buyer, ['approved_at' => null]);

        $this->postJson('/api/logistics/login', [
            'email' => $buyer->email,
            'password' => 'secret123',
        ])->assertStatus(403);

        $this->actingAs($buyer)
            ->get('/logistics/rider/entry')
            ->assertRedirect('/logistics/login');

        $this->assertSame(0, $buyer->tokens()->count());
    }

    public function test_inactive_account_linked_rider_is_rejected(): void
    {
        foreach (['inactive', 'suspended'] as $status) {
            $buyer = $this->user('buyer', ['status' => $status]);
            $this->rider($buyer);

            $this->postJson('/api/logistics/login', [
                'email' => $buyer->email,
                'password' => 'secret123',
            ])->assertStatus(403);
        }
    }

    public function test_inactive_rider_profile_is_rejected_by_api_and_web(): void
    {
        $buyer = $this->user('buyer');
        $this->rider($buyer, ['status' => 'inactive']);

        $this->postJson('/api/logistics/login', [
            'email' => $buyer->email,
            'password' => 'secret123',
        ])->assertStatus(403);

        $this->actingAs($buyer)
            ->get('/logistics/rider/entry')
            ->assertRedirect('/logistics/login');
    }

    public function test_logistics_admin_retains_access(): void
    {
        $admin = $this->globalAdmin();

        $this->actingAs($admin)
            ->get('/logistics/dashboard')
            ->assertOk();
    }
}
