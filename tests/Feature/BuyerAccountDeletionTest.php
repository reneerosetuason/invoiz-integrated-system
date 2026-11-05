<?php

namespace Tests\Feature;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuyerAccountDeletionDatabase;
use Tests\TestCase;

class BuyerAccountDeletionTest extends TestCase
{
    use BuyerAccountDeletionDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAccountDeletionDatabase();
    }

    public function test_buyer_can_submit_account_deletion_request()
    {
        $user = User::factory()->create([
            'role' => 'buyer',
            'account_status' => 'active',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->actingAs($user)->post('/profile/delete-account', [
            'reason' => 'I no longer use INVOIZ.',
            'current_password' => 'Password123!',
            'confirm_deletion' => '1',
        ]);

        $response->assertRedirect(route('buyer.account-deletion'));
        $this->assertDatabaseHas('account_deletion_requests', [
            'user_id' => $user->id,
            'reason' => 'I no longer use INVOIZ.',
            'status' => 'pending',
        ]);
    }

    public function test_buyer_can_cancel_account_deletion_request()
    {
        $user = User::factory()->create([
            'role' => 'buyer',
            'account_status' => 'active',
            'password' => Hash::make('Password123!'),
        ]);

        AccountDeletionRequest::create([
            'user_id' => $user->id,
            'reason' => 'I no longer use INVOIZ.',
            'requested_at' => now(),
            'scheduled_archive_at' => now()->addDays(30),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->post('/profile/cancel-deletion', [
            'current_password' => 'Password123!',
            'confirm_cancellation' => true,
        ]);

        $response->assertRedirect(route('buyer.account-deletion'));
        $this->assertDatabaseHas('account_deletion_requests', [
            'user_id' => $user->id,
            'status' => 'cancelled',
        ]);
    }
}
