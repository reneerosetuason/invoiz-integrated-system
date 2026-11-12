<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Support\BuyerSession;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class LogisticsLoginSessionIsolationTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        Notification::fake();
        Schema::create('riders', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->nullable(); $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('center_id')->nullable(); $table->softDeletes(); $table->timestamps();
        });
        Schema::create('logistics_center_applications', function (Blueprint $table) {
            $table->id(); $table->string('email'); $table->string('status'); $table->timestamp('provisioned_at')->nullable();
        });
    }

    private function account(string $role = 'logistics_staff', bool $verified = false): User
    {
        return User::create(['name' => 'Logistics Staff', 'email' => uniqid('role-').'@example.test',
            'password' => 'Password123!', 'role' => $role, 'status' => 'active', 'account_status' => 'active',
            'approval_status' => 'approved', 'is_admin' => false, 'email_verified_at' => $verified ? now() : null]);
    }

    public function test_logistics_login_preserves_verification_and_clears_stale_marketplace_state(): void
    {
        $staff = $this->account();
        $previousBuyer = $this->account('buyer', true);
        $this->withSession(['buyer' => ['id' => $previousBuyer->id], 'active_role' => 'buyer', 'seller_id' => 999, 'url.intended' => '/shop'])
            ->post('/logistics/login', ['email' => $staff->email, 'password' => 'Password123!'])
            ->assertRedirect(route('verification.notice', ['email' => $staff->email]))
            ->assertSessionMissing('buyer')->assertSessionMissing('active_role')->assertSessionMissing('seller_id');
        $this->assertAuthenticatedAs($staff);
        $this->assertNull($staff->fresh()->email_verified_at);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $staff->fresh()->otp);
        Notification::assertSentTo($staff, VerifyEmailNotification::class);
        $this->assertNull(BuyerSession::current());
        $this->get('/logistics/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_back_navigation_and_shop_cannot_turn_staff_into_a_buyer(): void
    {
        $staff = $this->account();
        $this->actingAs($staff)->get('/home')->assertRedirect('/logistics/dashboard');
        $this->get('/login')->assertRedirect('/logistics/dashboard');
        $this->get('/logistics/login')->assertRedirect('/home');
        $this->get('/shop')->assertRedirect('/logistics/dashboard');
        $this->get('/verify-email')->assertOk()->assertViewHas('email', $staff->email)
            ->assertSee('Back to Logistics login')->assertSee(route('logistics.logout'));
        $this->assertAuthenticatedAs($staff);
        $this->assertNull($staff->fresh()->email_verified_at);
    }

    public function test_verified_staff_ignore_marketplace_intended_destination(): void
    {
        $staff = $this->account(verified: true);
        $previousBuyer = $this->account('buyer', true);
        $this->withSession(['url.intended' => '/checkout', 'buyer' => ['id' => $previousBuyer->id]])
            ->post('/logistics/login', ['email' => $staff->email, 'password' => 'Password123!'])
            ->assertRedirect('/logistics/dashboard')->assertSessionMissing('buyer')->assertSessionMissing('url.intended');
        Notification::assertNothingSent();
    }

    public function test_valid_code_returns_staff_to_logistics_without_creating_a_buyer_snapshot(): void
    {
        $staff = $this->account();
        $staff->update(['otp' => '654321', 'otp_expires_at' => now()->addMinutes(10)]);
        $this->actingAs($staff)->post('/verify', ['email' => $staff->email, 'code' => '654321'])
            ->assertRedirect('/logistics/dashboard')->assertSessionMissing('buyer');
        $this->assertNotNull($staff->fresh()->email_verified_at);
        $this->assertNull($staff->fresh()->otp);
    }

    public function test_signed_verification_returns_staff_to_logistics(): void
    {
        $staff = $this->account();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $staff->id, 'hash' => sha1($staff->email)]);
        $this->get($url)->assertRedirect('/logistics/dashboard')->assertSessionMissing('buyer');
        $this->assertAuthenticatedAs($staff);
        $this->assertNotNull($staff->fresh()->email_verified_at);
    }

    public function test_back_to_logistics_login_logs_out_without_verifying(): void
    {
        $staff = $this->account();
        $this->actingAs($staff)->withSession(['buyer' => ['id' => 999]])->post('/logistics/logout')
            ->assertRedirect('/logistics/login')->assertSessionMissing('buyer');
        $this->assertGuest();
        $this->assertNull($staff->fresh()->email_verified_at);
    }

    public function test_logistics_user_cannot_use_legacy_buyer_mutations(): void
    {
        $staff = $this->account(verified: true);
        foreach (['/cart/add/1', '/buy/1', '/chat/send/1'] as $path) {
            $this->actingAs($staff)->postJson($path, ['body' => 'Blocked', 'qty' => 1])->assertForbidden();
        }
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_authentication_is_authoritative_over_a_stale_legacy_buyer_snapshot(): void
    {
        $buyer = $this->account('buyer', true); $other = $this->account('buyer', true);
        $this->actingAs($buyer)->withSession(['buyer' => ['id' => $other->id, 'email' => $other->email]]);
        $this->assertSame($buyer->id, BuyerSession::current()['id']);
        $this->get('/shop')->assertOk()->assertSessionMissing('buyer');
    }

    public function test_valid_legacy_buyer_and_public_guest_browsing_still_work(): void
    {
        $buyer = $this->account('buyer', true);
        $this->withSession(['buyer' => ['id' => $buyer->id]])->get('/shop')->assertOk();
        $this->assertSame($buyer->id, BuyerSession::current()['id']);
        session()->forget('buyer');
        $this->assertNull(BuyerSession::current());
        $this->get('/shop')->assertOk();
    }

    public function test_buyer_session_rejects_dedicated_logistics_accounts_even_with_legacy_snapshot(): void
    {
        $staff = $this->account(verified: true);
        session()->put('buyer', ['id' => $staff->id]);
        $this->assertNull(BuyerSession::current());
        $this->get('/shop')->assertOk()->assertSessionMissing('buyer');
    }

    public function test_marketplace_approval_flag_does_not_block_provisioned_staff_verification(): void
    {
        $staff = $this->account();
        $staff->update(['approval_status' => 'pending', 'otp' => '654321', 'otp_expires_at' => now()->addMinutes(10)]);
        $this->actingAs($staff)->post('/verify', ['email' => $staff->email, 'code' => '654321'])
            ->assertRedirect('/logistics/dashboard')->assertSessionMissing('buyer');
        $this->assertAuthenticatedAs($staff);
        $this->assertNotNull($staff->fresh()->email_verified_at);
    }

    public function test_pending_buyer_still_requires_marketplace_approval_after_email_verification(): void
    {
        $buyer = $this->account('buyer');
        $buyer->update(['approval_status' => 'pending', 'otp' => '654321', 'otp_expires_at' => now()->addMinutes(10)]);
        $this->post('/verify', ['email' => $buyer->email, 'code' => '654321'])->assertRedirect('/login')->assertSessionMissing('buyer');
        $this->assertGuest();
        $this->assertNotNull($buyer->fresh()->email_verified_at);
        $this->assertFalse($buyer->fresh()->canUseBuyerArea());
    }

    public function test_wrong_and_expired_codes_do_not_grant_staff_dashboard_access(): void
    {
        $staff = $this->account();
        $staff->update(['otp' => '654321', 'otp_expires_at' => now()->addMinutes(10)]);
        $this->actingAs($staff)->post('/verify', ['email' => $staff->email, 'code' => '111111'])->assertSessionHasErrors('code');
        $staff->update(['otp_expires_at' => now()->subMinute()]);
        $this->post('/verify', ['email' => $staff->email, 'code' => '654321'])->assertSessionHasErrors('code');
        $this->assertNull($staff->fresh()->email_verified_at);
        $this->get('/logistics/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_approved_rider_returns_to_rider_portal_after_code_verification(): void
    {
        $rider = $this->account('rider');
        \App\Models\Rider::create(['user_id' => $rider->id, 'status' => 'active', 'approved_at' => now()]);
        $rider->update(['otp' => '654321', 'otp_expires_at' => now()->addMinutes(10)]);
        $this->actingAs($rider)->post('/verify', ['email' => $rider->email, 'code' => '654321'])
            ->assertRedirect('/logistics/rider/entry')->assertSessionMissing('buyer');
    }
}
