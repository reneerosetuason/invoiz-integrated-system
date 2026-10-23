<?php

namespace Tests\Feature;

use App\Models\LogisticsCenter;
use App\Models\LogisticsCenterApplication;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerLifecycleConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('public');
    }

    private function user(array $overrides = []): User
    {
        $this->seq++;

        return User::create(array_merge([
            'name' => 'Lifecycle User '.$this->seq,
            'email' => 'phase13-user-'.$this->seq.'-'.Str::uuid().'@test.local',
            'password' => Hash::make('Password123!'),
            'role' => 'buyer',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'approval_status' => 'approved',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function admin(): User
    {
        return $this->user([
            'role' => 'admin',
            'is_admin' => true,
        ]);
    }

    private function seller(User $user, array $overrides = []): Seller
    {
        $this->seq++;

        return Seller::create(array_merge([
            'user_id' => $user->id,
            'store_name' => 'Lifecycle Store '.$this->seq,
            'business_name' => 'Lifecycle Store '.$this->seq,
            'line_of_business' => 'Retail',
            'approval_status' => 'pending',
            'status' => 'active',
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

    private function provisionedCenterUser(string $role = 'buyer'): User
    {
        $this->seq++;

        $center = LogisticsCenter::create([
            'name' => 'Phase 13 Center '.$this->seq,
            'address' => '1 Phase 13 Street',
            'city' => 'Test City',
            'is_active' => true,
        ]);

        $user = $this->user(['role' => $role, 'center_id' => $center->id]);

        LogisticsCenterApplication::create([
            'business_name' => 'Phase 13 Logistics '.$this->seq,
            'owner_name' => $user->name,
            'email' => $user->email,
            'phone' => '0917'.random_int(1000000, 9999999),
            'address' => '1 Phase 13 Street',
            'status' => 'approved',
            'provisioned_at' => now(),
        ]);

        return $user->fresh();
    }

    private function applicationPayload(string $email): array
    {
        $this->seq++;

        return [
            'first_name' => 'Applicant',
            'last_name' => 'Seller',
            'email' => $email,
            'phone' => '0917'.random_int(1000000, 9999999),
            'business_name' => 'Applicant Store '.$this->seq,
            'line_of_business' => 'Retail',
            'seller_type' => 'Sole Proprietorship',
            'tin' => str_pad((string) $this->seq, 12, '0', STR_PAD_LEFT),
            'region' => 'National Capital Region',
            'province' => 'Metro Manila',
            'city' => 'Quezon City',
            'barangay' => 'Barangay One',
            'address_line' => '1 Applicant Street',
            'postal_code' => '1100',
            'id_image' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
            'dti_certificate' => UploadedFile::fake()->create('dti.jpg', 100, 'image/jpeg'),
            'bir_2303' => UploadedFile::fake()->create('bir.jpg', 100, 'image/jpeg'),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => 'on',
        ];
    }

    private function login(User $user): \Illuminate\Testing\TestResponse
    {
        return $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);
    }

    public function test_new_applicant_stays_a_buyer_until_approval_and_pending_login_keeps_session(): void
    {
        $email = 'phase13-new-applicant-'.Str::uuid().'@test.local';

        $this->post('/sell', $this->applicationPayload($email))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $user = User::where('email', $email)->firstOrFail();
        $seller = Seller::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('buyer', $user->role);
        $this->assertSame('approved', $user->approval_status);
        $this->assertSame('pending', $seller->approval_status);
        $this->assertSame('active', $seller->status);
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->login($user)
            ->assertRedirect('/shop')
            ->assertSessionHas('error', 'Your seller application is still pending approval.');

        $this->assertAuthenticatedAs($user);

        $this->get('/buyer/chat')->assertOk();
    }

    public function test_existing_buyer_applying_to_sell_keeps_buyer_access_while_pending(): void
    {
        $buyer = $this->user();
        $payload = $this->applicationPayload($buyer->email);
        unset($payload['password'], $payload['password_confirmation']);

        $this->actingAs($buyer)
            ->post('/sell', $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $buyer = $buyer->fresh();
        $seller = Seller::where('user_id', $buyer->id)->firstOrFail();

        $this->assertSame('buyer', $buyer->role);
        $this->assertSame('pending', $seller->approval_status);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->login($buyer)
            ->assertRedirect('/shop')
            ->assertSessionHas('error', 'Your seller application is still pending approval.');

        $this->assertAuthenticatedAs($buyer);
        $this->get('/buyer/chat')->assertOk();
    }

    public function test_approval_promotes_a_buyer_and_preserves_buyer_and_seller_access(): void
    {
        $buyer = $this->user();
        $seller = $this->seller($buyer);

        $this->actingAs($this->admin())
            ->post("/admin/sellers/{$seller->id}/approve")
            ->assertRedirect();

        $buyer = $buyer->fresh();
        $seller = $seller->fresh();

        $this->assertSame('seller', $buyer->role);
        $this->assertSame('approved', $buyer->approval_status);
        $this->assertSame('approved', $seller->approval_status);
        $this->assertSame('active', $seller->status);
        $this->assertTrue($buyer->canSell());

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->login($buyer)->assertRedirect('/choose');
        $this->assertAuthenticatedAs($buyer);

        $this->post('/switch-role', ['role' => 'buyer'])->assertRedirect('/shop');
        $this->get('/buyer/chat')->assertOk();

        $this->post('/switch-role', ['role' => 'seller'])->assertRedirect('/seller/dashboard');
        $this->get('/seller/dashboard')->assertOk();
    }

    public function test_approval_preserves_an_approved_rider_primary_role(): void
    {
        $riderUser = $this->user(['role' => 'rider']);
        $this->rider($riderUser);
        $seller = $this->seller($riderUser);

        $this->actingAs($this->admin())
            ->post("/admin/sellers/{$seller->id}/approve")
            ->assertRedirect();

        $riderUser = $riderUser->fresh();

        $this->assertSame('rider', $riderUser->role);
        $this->assertTrue($riderUser->hasApprovedRiderProfile());
        $this->assertTrue($riderUser->canSell());
        $this->assertSame('approved', $seller->fresh()->approval_status);
    }

    public function test_approval_preserves_a_provisioned_logistics_primary_role(): void
    {
        $buyer = $this->provisionedCenterUser('buyer');
        $seller = $this->seller($buyer);

        $this->actingAs($this->admin())
            ->post("/admin/sellers/{$seller->id}/approve")
            ->assertRedirect();

        $buyer = $buyer->fresh();

        $this->assertSame('buyer', $buyer->role);
        $this->assertTrue($buyer->isLogisticsStaff());
        $this->assertTrue($buyer->canSell());
        $this->assertSame('approved', $seller->fresh()->approval_status);
    }

    public function test_approval_does_not_reactivate_a_suspended_account(): void
    {
        $buyer = $this->user([
            'account_status' => 'suspended',
            'status' => 'suspended',
        ]);
        $seller = $this->seller($buyer);

        $this->actingAs($this->admin())
            ->post("/admin/sellers/{$seller->id}/approve")
            ->assertRedirect();

        $buyer = $buyer->fresh();
        $seller = $seller->fresh();

        $this->assertSame('suspended', $buyer->account_status);
        $this->assertSame('suspended', $buyer->status);
        $this->assertFalse($buyer->isActive());
        $this->assertFalse($buyer->canSell());
        $this->assertSame('approved', $seller->approval_status);
        $this->assertSame('active', $seller->status);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->login($buyer)
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_rejected_new_applicant_remains_a_buyer_and_keeps_buyer_access(): void
    {
        $email = 'phase13-rejected-applicant-'.Str::uuid().'@test.local';

        $this->post('/sell', $this->applicationPayload($email))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user = User::where('email', $email)->firstOrFail();
        $seller = Seller::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($this->admin())
            ->post("/admin/sellers/{$seller->id}/reject", [
                'reason' => 'The submitted documents could not be verified.',
            ])
            ->assertRedirect();

        $user = $user->fresh();
        $seller = $seller->fresh();

        $this->assertSame('buyer', $user->role);
        $this->assertSame('approved', $user->approval_status);
        $this->assertSame('rejected', $seller->approval_status);
        $this->assertSame('inactive', $seller->status);
        $this->assertFalse($user->canSell());

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->login($user)->assertRedirect('/shop');
        $this->assertAuthenticatedAs($user);

        $this->get('/buyer/chat')->assertOk();
        $this->get('/seller/dashboard')->assertRedirect('/shop');
    }

    public function test_rejected_approved_seller_keeps_the_account_usable_as_a_buyer(): void
    {
        $sellerUser = $this->user(['role' => 'seller']);
        $seller = $this->seller($sellerUser, [
            'approval_status' => 'approved',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin())
            ->post("/admin/sellers/{$seller->id}/reject", [
                'reason' => 'The seller profile was rejected after review.',
            ])
            ->assertRedirect();

        $sellerUser = $sellerUser->fresh();
        $seller = $seller->fresh();

        $this->assertSame('seller', $sellerUser->role);
        $this->assertSame('approved', $sellerUser->approval_status);
        $this->assertFalse($sellerUser->canSell());
        $this->assertTrue($sellerUser->canUseBuyerArea());
        $this->assertSame('rejected', $seller->approval_status);
        $this->assertSame('inactive', $seller->status);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->login($sellerUser)->assertRedirect('/shop');
        $this->assertAuthenticatedAs($sellerUser);

        $this->get('/buyer/chat')->assertOk();
        $this->get('/seller/dashboard')->assertRedirect('/shop');
    }

    public function test_inactive_seller_profile_is_not_approved_or_sellable(): void
    {
        $buyer = $this->user();
        $seller = $this->seller($buyer, [
            'approval_status' => 'approved',
            'status' => 'inactive',
        ]);

        $this->assertFalse($seller->isApproved());
        $this->assertFalse(Seller::approved()->whereKey($seller->id)->exists());
        $this->assertFalse($buyer->fresh()->canSell());

        $this->actingAs($buyer)
            ->get('/seller/dashboard')
            ->assertRedirect('/shop');

        $this->get('/buyer/chat')->assertOk();
    }

    public function test_account_status_takes_precedence_over_legacy_status_for_access(): void
    {
        $buyer = $this->user();
        $this->forceStatusMismatch($buyer);
        $this->actingAs($buyer)->get('/buyer/chat')->assertRedirect('/login');
        $this->assertGuest();

        $admin = $this->admin();
        $this->forceStatusMismatch($admin);
        $this->actingAs($admin)->get('/admin/manage-accounts')->assertRedirect('/admin/login');
        $this->assertGuest();

        $sellerUser = $this->user(['role' => 'seller']);
        $this->seller($sellerUser, ['approval_status' => 'approved', 'status' => 'active']);
        $this->forceStatusMismatch($sellerUser);
        $this->actingAs($sellerUser)->get('/seller/dashboard')->assertRedirect('/seller/login');
        $this->assertGuest();

        $staff = $this->user(['role' => 'logistics_admin']);
        $this->forceStatusMismatch($staff);

        $this->post('/logistics/login', [
            'email' => $staff->email,
            'password' => 'Password123!',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->actingAs($staff)->get('/logistics/dashboard')->assertRedirect('/logistics/login');
        $this->assertGuest();

        $riderUser = $this->user(['role' => 'rider']);
        $this->rider($riderUser);
        $this->forceStatusMismatch($riderUser);
        $this->actingAs($riderUser)->get('/logistics/rider/entry')->assertRedirect('/logistics/login');

        Sanctum::actingAs($riderUser->fresh());
        $this->getJson('/api/logistics/rider/profile')->assertForbidden();
    }

    public function test_seller_login_rejects_a_suspended_account_even_with_an_active_seller_profile(): void
    {
        $sellerUser = $this->user([
            'role' => 'seller',
            'account_status' => 'suspended',
            'status' => 'suspended',
        ]);
        $this->seller($sellerUser, [
            'approval_status' => 'approved',
            'status' => 'active',
        ]);

        $this->post('/seller/login', [
            'email' => $sellerUser->email,
            'password' => 'Password123!',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_legacy_seller_application_state_does_not_block_buyer_api_login(): void
    {
        $pending = $this->user([
            'role' => 'seller',
            'approval_status' => 'pending',
        ]);
        $this->seller($pending, [
            'approval_status' => 'pending',
            'status' => 'active',
        ]);

        $this->postJson('/api/login', [
            'email' => $pending->email,
            'password' => 'Password123!',
        ])->assertOk()
            ->assertJsonPath('user.approval_status', 'pending')
            ->assertJsonStructure(['token']);

        $rejected = $this->user([
            'role' => 'seller',
            'approval_status' => 'rejected',
        ]);
        $this->seller($rejected, [
            'approval_status' => 'rejected',
            'status' => 'inactive',
        ]);

        $this->postJson('/api/login', [
            'email' => $rejected->email,
            'password' => 'Password123!',
        ])->assertOk()
            ->assertJsonPath('user.role', 'seller')
            ->assertJsonStructure(['token']);

        $blocked = $this->user(['approval_status' => 'rejected']);

        $this->postJson('/api/login', [
            'email' => $blocked->email,
            'password' => 'Password123!',
        ])->assertForbidden();
    }

    public function test_pending_or_rejected_buyer_without_seller_cannot_use_buyer_area(): void
    {
        foreach (['pending', 'rejected'] as $approvalStatus) {
            $buyer = $this->user(['approval_status' => $approvalStatus]);

            $this->assertFalse($buyer->canUseBuyerArea());

            $this->actingAs($buyer)
                ->get('/buyer/chat')
                ->assertRedirect('/login');

            $this->assertGuest();

            $this->login($buyer)
                ->assertRedirect()
                ->assertSessionHasErrors('email');

            $this->assertGuest();

            $this->actingAs($buyer)
                ->get('/seller/dashboard')
                ->assertRedirect('/shop');
        }
    }

    public function test_verify_route_rejects_inactive_and_unapproved_accounts(): void
    {
        $suspended = $this->user([
            'account_status' => 'suspended',
            'status' => 'suspended',
        ]);

        $this->from('/verify')->post('/verify', [
            'email' => $suspended->email,
            'code' => '123456',
        ])->assertRedirect('/verify')->assertSessionHasErrors('email');

        $this->assertGuest();

        $rejected = $this->user(['approval_status' => 'rejected']);

        $this->from('/verify')->post('/verify', [
            'email' => $rejected->email,
            'code' => '123456',
        ])->assertRedirect('/verify')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_google_login_rejects_inactive_and_unapproved_accounts(): void
    {
        $accounts = [
            $this->user([
                'account_status' => 'suspended',
                'status' => 'suspended',
            ]),
            $this->user(['approval_status' => 'rejected']),
        ];

        foreach ($accounts as $account) {
            Http::fake([
                'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token'], 200),
                'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                    'email' => $account->email,
                    'name' => $account->name,
                ], 200),
            ]);

            $this->withSession(['google_oauth_state' => 'phase13-state'])
                ->get('/auth/google/callback?state=phase13-state&code=test-code')
                ->assertRedirect('/login')
                ->assertSessionHasErrors('email');

            $this->assertGuest();
        }
    }

    public function test_active_account_status_overrides_a_legacy_suspended_status(): void
    {
        $buyer = $this->user([
            'account_status' => 'active',
            'status' => 'suspended',
        ]);

        $this->assertTrue($buyer->isActive());
        $this->assertTrue($buyer->canUseBuyerArea());

        $this->actingAs($buyer)->get('/buyer/chat')->assertOk();
    }

    public function test_rejection_preserves_approved_rider_and_logistics_access(): void
    {
        $riderUser = $this->user(['role' => 'rider']);
        $this->rider($riderUser);
        $riderSeller = $this->seller($riderUser);

        $this->actingAs($this->admin())
            ->post("/admin/sellers/{$riderSeller->id}/reject", [
                'reason' => 'Seller documents were incomplete.',
            ])->assertRedirect();

        $riderUser = $riderUser->fresh();
        $this->assertSame('rider', $riderUser->role);
        $this->assertTrue($riderUser->hasApprovedRiderProfile());
        $this->assertFalse($riderUser->canSell());

        $this->actingAs($riderUser)->get('/logistics/rider/entry')->assertOk();

        $logisticsUser = $this->provisionedCenterUser('buyer');
        $logisticsSeller = $this->seller($logisticsUser);

        $this->actingAs($this->admin())
            ->post("/admin/sellers/{$logisticsSeller->id}/reject", [
                'reason' => 'Seller documents were incomplete.',
            ])->assertRedirect();

        $logisticsUser = $logisticsUser->fresh();
        $this->assertSame('buyer', $logisticsUser->role);
        $this->assertTrue($logisticsUser->isLogisticsStaff());
        $this->assertFalse($logisticsUser->canSell());

        $this->actingAs($logisticsUser)->get('/logistics/dashboard')->assertOk();
    }

    public function test_suspended_logistics_admin_is_signed_out(): void
    {
        $admin = $this->user([
            'role' => 'logistics_admin',
            'account_status' => 'suspended',
            'status' => 'suspended',
        ]);

        $this->actingAs($admin)
            ->get('/logistics/dashboard')
            ->assertRedirect('/logistics/login')
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_reinstating_a_suspended_seller_restores_only_entitled_access(): void
    {
        $sellerUser = $this->user(['role' => 'seller']);
        $seller = $this->seller($sellerUser, [
            'approval_status' => 'approved',
            'status' => 'active',
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post("/admin/sellers/{$seller->id}/suspend")
            ->assertRedirect();

        $sellerUser = $sellerUser->fresh();
        $this->assertFalse($sellerUser->isActive());
        $this->assertFalse($sellerUser->canSell());

        $this->actingAs($admin)
            ->post("/admin/sellers/{$seller->id}/reinstate")
            ->assertRedirect();

        $sellerUser = $sellerUser->fresh();
        $seller = $seller->fresh();

        $this->assertTrue($sellerUser->isActive());
        $this->assertSame('seller', $sellerUser->role);
        $this->assertSame('approved', $seller->approval_status);
        $this->assertSame('active', $seller->status);
        $this->assertTrue($sellerUser->canSell());

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->login($sellerUser)->assertRedirect('/choose');
        $this->assertAuthenticatedAs($sellerUser);
    }

    private function forceStatusMismatch(User $user): void
    {
        DB::table('users')->where('id', $user->id)->update([
            'account_status' => 'suspended',
            'status' => 'active',
        ]);

        $user->refresh();
    }
}
