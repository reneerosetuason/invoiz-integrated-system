<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\Notification;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Integration coverage for the logistics merge (commits 13319f9 / 311eaee).
 *
 * Uses DatabaseTransactions (wrap + rollback) rather than RefreshDatabase,
 * because the repository's migration chain cannot bootstrap a fresh schema
 * (see the audit report). The dedicated invoiz_ecommerce_test schema is
 * pre-built; every row written here is rolled back.
 *
 * Protected by Tests\TestCase::guardAgainstDestructiveDatabase().
 */
class LogisticsMergeTest extends TestCase
{
    use DatabaseTransactions;

    private function riderUser(string $email = 'rider@test.local'): User
    {
        $user = User::create([
            'name' => 'Test Rider',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => 'active',
        ]);

        Rider::create([
            'user_id' => $user->id,
            'name' => 'Test Rider',
            'email' => $email,
            'phone' => '+639171234567',
            'vehicle_type' => 'motorcycle',
            'approved_at' => now(),
        ]);

        return $user->fresh();
    }

    private function delivery(User $owner, string $status = 'delivery_failed', array $extra = []): Delivery
    {
        return Delivery::create(array_merge([
            'rider_id' => Rider::where('user_id', $owner->id)->value('id'),
            'status' => $status,
            'tracking_number' => 'TRK-'.strtoupper(bin2hex(random_bytes(3))),
            'sender_name' => 'Sender',
            'recipient_name' => 'Recipient',
            'sender_address' => 'Somewhere',
            'recipient_address' => 'Elsewhere',
        ], $extra));
    }

    // ------------------------------------------------ requestReattempt

    public function test_reattempt_requires_ownership(): void
    {
        $owner = $this->riderUser('owner@test.local');
        $intruder = $this->riderUser('intruder@test.local');
        $delivery = $this->delivery($owner);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/reattempt")
            ->assertForbidden();

        $this->assertNull($delivery->fresh()->reattempt_requested_at);
    }

    public function test_reattempt_rejected_when_not_failed(): void
    {
        $user = $this->riderUser();
        $delivery = $this->delivery($user, 'out_for_delivery');

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/reattempt")
            ->assertStatus(409)
            ->assertJsonPath('errors.status.0', "Invalid status transition from 'out_for_delivery'.");
    }

    public function test_reattempt_succeeds_and_persists(): void
    {
        $user = $this->riderUser();
        $delivery = $this->delivery($user);

        Sanctum::actingAs($user);

        $resp = $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/reattempt")
            ->assertOk()
            ->assertJsonPath('message', 'Re-attempt requested. Logistics will review and reassign the delivery.');

        $fresh = $delivery->fresh();
        $this->assertNotNull($fresh->reattempt_requested_at, 'reattempt_requested_at must be persisted');
        $this->assertSame('delivery_failed', $fresh->status, 'status must stay delivery_failed');

        $this->assertTrue(
            DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'reattempt_requested')->exists(),
            'status log row must be written'
        );
        $this->assertTrue(
            Notification::where('type', 'reattempt_requested')->exists(),
            'notification must be created'
        );

        // payload exposes the new column
        $this->assertArrayHasKey('reattempt_requested_at', $resp->json('delivery'));
    }

    public function test_reattempt_is_idempotent_per_failure_cycle(): void
    {
        $user = $this->riderUser();
        $delivery = $this->delivery($user);

        Sanctum::actingAs($user);
        $url = "/api/logistics/rider/deliveries/{$delivery->id}/reattempt";

        $this->postJson($url)->assertOk();
        $this->postJson($url)
            ->assertStatus(409)
            ->assertJsonPath('message', 'A re-attempt has already been requested for this delivery.');

        $this->assertSame(
            1,
            DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'reattempt_requested')->count(),
            'second call must not write a duplicate log'
        );
    }

    public function test_reattempt_blocked_after_parcel_returned(): void
    {
        $user = $this->riderUser();
        $delivery = $this->delivery($user, 'delivery_failed', [
            'returned_at' => now(),
            'return_reason' => 'already returned',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/reattempt")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This parcel was already returned to the sorting center.');
    }

    // ---------------------------------------------- returnToCenter

    public function test_return_requires_ownership(): void
    {
        $owner = $this->riderUser('owner2@test.local');
        $intruder = $this->riderUser('intruder2@test.local');
        $delivery = $this->delivery($owner);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/return-to-center")
            ->assertForbidden();
    }

    public function test_return_to_center_persists_and_supersedes_reattempt(): void
    {
        $user = $this->riderUser();
        $delivery = $this->delivery($user, 'delivery_failed', [
            'failure_reason' => 'Recipient refused',
            'reattempt_requested_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $resp = $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/return-to-center")
            ->assertOk()
            ->assertJsonPath('message', 'Parcel marked as returned to the sorting center.');

        $fresh = $delivery->fresh();
        $this->assertNotNull($fresh->returned_at, 'returned_at must be persisted');
        $this->assertSame('Recipient refused', $fresh->return_reason, 'failure_reason must be preserved');
        $this->assertNull($fresh->reattempt_requested_at, 'returning must supersede a pending re-attempt');

        $this->assertTrue(
            DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'returned_to_center')->exists()
        );
        $this->assertTrue(Notification::where('type', 'parcel_returned_to_center')->exists());

        $payload = $resp->json('delivery');
        $this->assertArrayHasKey('returned_at', $payload);
        $this->assertArrayHasKey('return_reason', $payload);
    }

    public function test_return_to_center_is_idempotent(): void
    {
        $user = $this->riderUser();
        $delivery = $this->delivery($user, 'delivery_failed', ['failure_reason' => 'x']);

        Sanctum::actingAs($user);
        $url = "/api/logistics/rider/deliveries/{$delivery->id}/return-to-center";

        $this->postJson($url)->assertOk();
        $this->postJson($url)
            ->assertStatus(409)
            ->assertJsonPath('message', 'This parcel was already returned to the sorting center.');
    }

    public function test_return_rejected_when_not_failed(): void
    {
        $user = $this->riderUser();
        $delivery = $this->delivery($user, 'picked_up');

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/return-to-center")
            ->assertStatus(409);
    }

    // ------------------------------------------ password reset

    public function test_forgot_is_anti_enumeration_for_unknown_email(): void
    {
        NotificationFacade::fake();

        $this->postJson('/api/logistics/rider/password/forgot', ['email' => 'nobody@nowhere.test'])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'If an active rider account exists for that email address, a password reset link has been sent.'
            );
    }

    public function test_forgot_returns_identical_response_for_a_real_rider(): void
    {
        NotificationFacade::fake();
        $rider = $this->riderUser('realrider@test.local');

        $resp = $this->postJson('/api/logistics/rider/password/forgot', ['email' => 'realrider@test.local'])
            ->assertOk();

        $this->assertSame(
            'If an active rider account exists for that email address, a password reset link has been sent.',
            $resp->json('message'),
            'identical message for known and unknown addresses'
        );
        $this->assertTrue(
            DB::table('password_reset_tokens')->where('email', 'realrider@test.local')->exists(),
            'a broker token must be created for a real active rider'
        );
    }

    public function test_forgot_does_not_leak_for_inactive_or_non_rider_accounts(): void
    {
        NotificationFacade::fake();

        $buyer = User::create([
            'name' => 'Buyer', 'email' => 'buyer@test.local',
            'password' => Hash::make('x'), 'role' => 'buyer', 'status' => 'active',
        ]);
        $inactive = User::create([
            'name' => 'Inactive Rider', 'email' => 'inactive@test.local',
            'password' => Hash::make('x'), 'role' => 'rider', 'status' => 'inactive',
        ]);

        foreach (['buyer@test.local', 'inactive@test.local'] as $email) {
            $resp = $this->postJson('/api/logistics/rider/password/forgot', ['email' => $email])->assertOk();
            $this->assertStringContainsString('If an active rider account exists', $resp->json('message'));
            $this->assertFalse(
                DB::table('password_reset_tokens')->where('email', $email)->exists(),
                "no token may be issued for {$email}"
            );
        }
    }

    public function test_reset_rejects_bad_token_with_generic_error(): void
    {
        $rider = $this->riderUser('resetrider@test.local');

        $resp = $this->postJson('/api/logistics/rider/password/reset', [
            'email' => 'resetrider@test.local',
            'token' => 'not-a-real-token',
            'password' => 'NewPassw0rd!',
            'password_confirmation' => 'NewPassw0rd!',
        ])->assertStatus(422);

        $this->assertSame(
            'This password reset link is invalid or has expired. Please request a new one.',
            $resp->json('message')
        );
    }

    public function test_reset_rejects_unknown_email_identically(): void
    {
        $resp = $this->postJson('/api/logistics/rider/password/reset', [
            'email' => 'ghost@nowhere.test',
            'token' => 'whatever',
            'password' => 'NewPassw0rd!',
            'password_confirmation' => 'NewPassw0rd!',
        ])->assertStatus(422);

        $this->assertSame(
            'This password reset link is invalid or has expired. Please request a new one.',
            $resp->json('message'),
            'unknown email must be indistinguishable from a bad token'
        );
    }

    public function test_reset_rotates_password_and_invalidates_token_and_api_sessions(): void
    {
        $rider = $this->riderUser('rotate@test.local');
        $originalHash = $rider->password;

        // an existing API session must be revoked by the reset
        $rider->createToken('existing-device');

        $broker = Password::broker();
        $token = $broker->createToken($rider);

        $this->assertTrue(
            DB::table('password_reset_tokens')->where('email', $rider->email)->exists()
        );

        $this->postJson('/api/logistics/rider/password/reset', [
            'email' => $rider->email,
            'token' => $token,
            'password' => 'BrandNewPass1!',
            'password_confirmation' => 'BrandNewPass1!',
        ])->assertOk()
            ->assertJsonPath('message', 'Password reset successfully. You may now log in with your new password.');

        $this->assertTrue(Hash::check('BrandNewPass1!', $rider->fresh()->password), 'password must be rotated');
        $this->assertNotSame($originalHash, $rider->fresh()->password);

        $this->assertFalse(
            DB::table('password_reset_tokens')->where('email', $rider->email)->exists(),
            'reset token must be single-use (deleted on success)'
        );
        $this->assertSame(
            0,
            DB::table('personal_access_tokens')->where('tokenable_id', $rider->id)->count(),
            'all API sessions must be revoked on reset'
        );
    }

    public function test_reset_token_cannot_be_replayed(): void
    {
        $rider = $this->riderUser('replay@test.local');
        $token = Password::broker()->createToken($rider);

        $payload = [
            'email' => $rider->email,
            'token' => $token,
            'password' => 'FirstNewPass1!',
            'password_confirmation' => 'FirstNewPass1!',
        ];

        $this->postJson('/api/logistics/rider/password/reset', $payload)->assertOk();

        // replay the very same token
        $this->postJson('/api/logistics/rider/password/reset', [
            'email' => $rider->email,
            'token' => $token,
            'password' => 'SecondNewPass1!',
            'password_confirmation' => 'SecondNewPass1!',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('FirstNewPass1!', $rider->fresh()->password), 'replay must not change the password');
    }

    public function test_reset_enforces_password_policy(): void
    {
        $rider = $this->riderUser('weak@test.local');
        $token = Password::broker()->createToken($rider);

        $this->postJson('/api/logistics/rider/password/reset', [
            'email' => $rider->email,
            'token' => $token,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    // ------------------------------------------------ audit fix #7: rollback

    /**
     * Force the LAST write of the operation to fail and assert that every
     * earlier write in the same transaction is rolled back, leaving the
     * delivery exactly as it was (so the rider can legitimately retry).
     */
    private function failNotificationWrite(): void
    {
        Notification::creating(function (): void {
            throw new \RuntimeException('simulated notification write failure');
        });
    }

    private function forgetNotificationHook(): void
    {
        Event::forget('eloquent.creating: '.Notification::class);
    }

    private function callAndCapture(string $url): array
    {
        $thrown = null;
        $response = null;

        try {
            $response = $this->postJson($url);
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        return [$response, $thrown];
    }

    public function test_reattempt_rolls_back_every_write_when_the_notification_fails(): void
    {
        $user = $this->riderUser();
        $delivery = $this->delivery($user);

        Sanctum::actingAs($user);
        $url = "/api/logistics/rider/deliveries/{$delivery->id}/reattempt";

        $this->failNotificationWrite();

        try {
            [$response, $thrown] = $this->callAndCapture($url);

            if ($thrown === null) {
                $this->assertSame(500, $response->status(), 'a failed write must surface as a server error');
            } else {
                $this->assertStringContainsString('simulated notification write failure', $thrown->getMessage());
            }
        } finally {
            $this->forgetNotificationHook();
        }

        $after = $delivery->fresh();

        $this->assertNull(
            $after->reattempt_requested_at,
            'reattempt_requested_at must be rolled back when a later write fails'
        );
        $this->assertSame(
            0,
            DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'reattempt_requested')->count(),
            'the status log write must be rolled back too'
        );
        $this->assertSame(
            0,
            Notification::where('type', 'reattempt_requested')->count(),
            'no notification may survive a rolled-back transaction'
        );

        // The rider must still be able to complete the request afterwards.
        $this->postJson($url)->assertOk();
        $this->assertNotNull($delivery->fresh()->reattempt_requested_at, 'a retried request must succeed');
    }

    public function test_return_to_center_rolls_back_every_write_when_the_notification_fails(): void
    {
        $user = $this->riderUser();
        $delivery = $this->delivery($user, 'delivery_failed', ['failure_reason' => 'Recipient refused']);

        Sanctum::actingAs($user);
        $url = "/api/logistics/rider/deliveries/{$delivery->id}/return-to-center";

        $this->failNotificationWrite();

        try {
            [$response, $thrown] = $this->callAndCapture($url);

            if ($thrown === null) {
                $this->assertSame(500, $response->status());
            } else {
                $this->assertStringContainsString('simulated notification write failure', $thrown->getMessage());
            }
        } finally {
            $this->forgetNotificationHook();
        }

        $after = $delivery->fresh();

        $this->assertNull($after->returned_at, 'returned_at must be rolled back');
        $this->assertNull($after->return_reason, 'return_reason must be rolled back');
        $this->assertSame(
            0,
            DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'returned_to_center')->count(),
            'the status log write must be rolled back too'
        );
        $this->assertSame(0, Notification::where('type', 'parcel_returned_to_center')->count());

        $this->postJson($url)->assertOk();
        $after = $delivery->fresh();
        $this->assertNotNull($after->returned_at, 'a retried request must succeed');
        $this->assertSame('Recipient refused', $after->return_reason, 'failure reason still preserved on retry');
    }

    // ------------------------------------------------ audit fix #4: migration guard

    public function test_reattempt_migration_up_is_a_noop_when_the_columns_already_exist(): void
    {
        // Every column this migration adds is already present in this schema,
        // so a correctly guarded up() must be a silent no-op. An unguarded
        // addColumn would raise "Duplicate column name".
        $this->assertTrue(Schema::hasColumn('deliveries', 'reattempt_requested_at'), 'precondition: column exists');
        $this->assertTrue(Schema::hasColumn('deliveries', 'returned_at'), 'precondition: column exists');
        $this->assertTrue(Schema::hasColumn('deliveries', 'return_reason'), 'precondition: column exists');

        $migration = require __DIR__.'/../../database/migrations/logistics/2026_10_07_000002_add_reattempt_requested_at_to_deliveries_table.php';

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('deliveries', 'reattempt_requested_at'));
        $this->assertTrue(Schema::hasColumn('deliveries', 'returned_at'));
        $this->assertTrue(Schema::hasColumn('deliveries', 'return_reason'));
    }
}
