<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailVerificationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function createBuyer(array $overrides = []): User
    {
        $this->seq++;
        return User::create(array_merge([
            'name' => 'Test Buyer ' . $this->seq,
            'first_name' => 'Test',
            'last_name' => 'Buyer ' . $this->seq,
            'email' => 'buyer-verify-' . $this->seq . '-' . Str::uuid() . '@invoiz.test',
            'password' => Hash::make('Password123!'),
            'role' => 'buyer',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'approval_status' => 'approved',
            'email_verified_at' => null,
            'otp' => '654321',
            'otp_expires_at' => now()->addMinutes(10),
        ], $overrides));
    }

    public function test_new_registration_dispatches_registered_event_and_verification_notification(): void
    {
        Notification::fake();
        Event::fake([Registered::class]);

        $email = 'register-' . Str::uuid() . '@invoiz.test';

        $response = $this->post(route('register.post'), [
            'first_name' => 'Juan',
            'last_name' => 'Luna',
            'sex' => 'male',
            'birthday' => '1998-06-12',
            'email' => $email,
            'phone' => '0918' . random_int(1000000, 9999999),
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'terms' => '1',
        ]);

        $response->assertRedirect('/verify?email=' . urlencode($email));
        Event::assertDispatched(Registered::class);

        $user = User::where('email', $email)->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->otp);
    }

    public function test_valid_signed_verification_link_verifies_account_and_redirects_to_destination(): void
    {
        Event::fake([Verified::class]);
        $user = $this->createBuyer();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->get($verificationUrl);

        $response->assertRedirect('/shop');
        $response->assertSessionHas('success');

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->otp);

        Event::assertDispatched(Verified::class);
    }

    public function test_already_verified_account_visiting_signed_link_is_handled_gracefully(): void
    {
        $user = $this->createBuyer(['email_verified_at' => now()]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->get($verificationUrl);

        $response->assertRedirect('/shop');
        $response->assertSessionHas('success', 'Your email is already verified.');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_signature_verification_link_is_rejected(): void
    {
        $user = $this->createBuyer();

        $invalidUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
        ) . 'tampered';

        $response = $this->get($invalidUrl);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('error');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_expired_verification_link_is_rejected(): void
    {
        $user = $this->createBuyer();

        $expiredUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->subMinutes(10),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->get($expiredUrl);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('error');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_mismatched_hash_verification_link_is_rejected(): void
    {
        $user = $this->createBuyer();

        $mismatchedUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1('wrong-email@invoiz.test')]
        );

        $response = $this->get($mismatchedUrl);

        $response->assertRedirect('/verify?email=' . urlencode($user->email));
        $response->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_unverified_user_cannot_access_protected_buyer_page(): void
    {
        $user = $this->createBuyer();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertRedirect(route('verification.notice', ['email' => $user->email]));
        $response->assertSessionHas('error', 'Please verify your email address first.');
    }

    public function test_resend_verification_sends_notification_and_is_rate_limited(): void
    {
        Notification::fake();
        $user = $this->createBuyer();

        $response1 = $this->from('/verify')->post('/email/verification-notification', [
            'email' => $user->email,
        ]);

        $response1->assertRedirect();
        $response1->assertSessionHas('success');
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        // Immediate subsequent request should be rate-limited by the throttle key
        $response2 = $this->from('/verify')->post('/email/verification-notification', [
            'email' => $user->email,
        ]);

        $response2->assertSessionHas('error');
    }
}
