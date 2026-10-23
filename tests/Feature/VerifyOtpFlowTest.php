<?php

namespace Tests\Feature;

use App\Mail\OtpCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class VerifyOtpFlowTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function user(array $overrides = []): User
    {
        $this->seq++;

        return User::create(array_merge([
            'name' => 'Verify User '.$this->seq,
            'first_name' => 'Verify',
            'last_name' => 'User '.$this->seq,
            'email' => 'phase13c-'.$this->seq.'-'.Str::uuid().'@test.local',
            'password' => Hash::make('Password123!'),
            'role' => 'buyer',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'approval_status' => 'approved',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function unverifiedUser(array $overrides = []): User
    {
        return $this->user(array_merge([
            'email_verified_at' => null,
            'otp' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ], $overrides));
    }

    private function verify(User|string $user, string $code): TestResponse
    {
        return $this->from('/verify')->post('/verify', [
            'email' => $user instanceof User ? $user->email : $user,
            'code' => $code,
        ]);
    }

    public function test_already_verified_account_cannot_authenticate_without_otp(): void
    {
        $user = $this->user(['otp' => null, 'otp_expires_at' => null]);

        $this->verify($user, '000000')
            ->assertRedirect('/login')
            ->assertSessionHas('success');

        $this->assertGuest();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->otp);
        $this->assertSame('buyer', $user->role);
        $this->assertSame('approved', $user->approval_status);
        $this->assertSame('active', $user->account_status);
    }

    public function test_invalid_otp_does_not_authenticate_or_change_account_state(): void
    {
        $user = $this->unverifiedUser();

        $this->verify($user, '654321')
            ->assertRedirect('/verify')
            ->assertSessionHasErrors('code');

        $this->assertGuest();

        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('123456', $user->otp);
        $this->assertSame('buyer', $user->role);
        $this->assertSame('approved', $user->approval_status);
        $this->assertSame('active', $user->account_status);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = $this->unverifiedUser(['otp_expires_at' => now()->subMinute()]);

        $this->verify($user, '123456')
            ->assertRedirect('/verify')
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_valid_otp_verifies_and_authenticates_the_account(): void
    {
        $user = $this->unverifiedUser();

        $this->verify($user, '123456')
            ->assertRedirect('/shop')
            ->assertSessionHas('success');

        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->id, session('buyer.id'));

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->otp);
        $this->assertNull($user->otp_expires_at);
    }

    public function test_reused_otp_cannot_authenticate_a_new_session(): void
    {
        $user = $this->unverifiedUser();

        $this->verify($user, '123456')->assertRedirect('/shop');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->verify($user, '123456')
            ->assertRedirect('/login')
            ->assertSessionHas('success');

        $this->assertGuest();
        $this->assertNull($user->fresh()->otp);
    }

    public function test_another_accounts_otp_cannot_authenticate(): void
    {
        $owner = $this->unverifiedUser(['otp' => '111111']);
        $other = $this->unverifiedUser(['otp' => '222222']);

        $this->verify($other, '111111')
            ->assertRedirect('/verify')
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertNull($other->fresh()->email_verified_at);
        $this->assertNull($owner->fresh()->email_verified_at);

        $this->verify($owner, '111111')->assertRedirect('/shop');
        $this->assertAuthenticatedAs($owner);
    }

    public function test_otp_attempts_are_limited_and_resend_clears_the_lockout(): void
    {
        $user = $this->unverifiedUser();

        for ($i = 0; $i < 5; $i++) {
            $this->verify($user, '654321')
                ->assertRedirect('/verify')
                ->assertSessionHasErrors('code');
            $this->assertGuest();
        }

        $this->verify($user, '123456')
            ->assertRedirect('/verify')
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        $user->refresh();
        $this->assertSame('123456', $user->otp);
        $this->assertNull($user->email_verified_at);

        $this->from('/verify')->post('/verify/resend', ['email' => $user->email])
            ->assertRedirect('/verify')
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertNotNull($user->otp);
        $this->assertNotNull($user->otp_expires_at);

        $this->verify($user, $user->otp)->assertRedirect('/shop');
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_verified_user_remains_authenticated(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->from('/verify')
            ->post('/verify', ['email' => $user->email, 'code' => '000000'])
            ->assertRedirect('/shop');

        $this->assertAuthenticatedAs($user);
    }

    public function test_unverified_login_redirects_to_verify_and_accepts_otp(): void
    {
        $user = $this->unverifiedUser(['otp' => null, 'otp_expires_at' => null]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertRedirect('/verify?email='.urlencode($user->email))
            ->assertSessionHas('success');

        $this->assertGuest();
        Mail::assertSent(OtpCodeMail::class, 1);

        $user->refresh();
        $this->assertNotNull($user->otp);
        $this->assertNotNull($user->otp_expires_at);

        $this->verify($user, $user->otp)->assertRedirect('/shop');
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_and_resend_issue_a_usable_otp(): void
    {
        $email = 'phase13c-register-'.Str::uuid().'@test.local';

        $this->post(route('register.post'), [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'sex' => 'female',
            'birthday' => '1995-05-31',
            'email' => $email,
            'phone' => '0917'.random_int(1000000, 9999999),
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
            'terms' => '1',
        ])->assertRedirect('/verify?email='.urlencode($email))
            ->assertSessionHasNoErrors();

        $user = User::where('email', $email)->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->otp);
        $this->assertNotNull($user->otp_expires_at);
        Mail::assertSent(OtpCodeMail::class, 1);

        $this->from('/verify')->post('/verify/resend', ['email' => $email])
            ->assertRedirect('/verify')
            ->assertSessionHas('success');

        Mail::assertSent(OtpCodeMail::class, 2);

        $user->refresh();
        $this->assertNotNull($user->otp);
        $this->assertNotNull($user->otp_expires_at);

        $this->verify($user, $user->otp)->assertRedirect('/shop');
        $this->assertAuthenticatedAs($user);
    }
}
