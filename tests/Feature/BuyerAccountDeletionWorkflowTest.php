<?php

namespace Tests\Feature;

use App\Models\AccountDeletionRequest;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\BuyerAccountDeletionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuyerAccountDeletionDatabase;
use Tests\TestCase;

class BuyerAccountDeletionWorkflowTest extends TestCase
{
    use BuyerAccountDeletionDatabase;

    private User $buyer;

    private BuyerAccountDeletionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAccountDeletionDatabase();
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0, 0));
        $this->buyer = $this->account();
        $this->service = app(BuyerAccountDeletionService::class);
        $this->actingAs($this->buyer);
    }

    private function account(array $overrides = []): User
    {
        return User::create($overrides + ['name' => 'Deletion Test', 'email' => uniqid().'@example.test',
            'password' => 'Password123!', 'role' => 'buyer', 'is_admin' => false, 'account_status' => 'active',
            'status' => 'active', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function input(array $overrides = []): array
    {
        return $overrides + ['reason' => BuyerAccountDeletionService::REASONS[0], 'current_password' => 'Password123!', 'confirm_deletion' => true];
    }

    private function submitted(bool $due = false): AccountDeletionRequest
    {
        $record = $this->service->submit($this->buyer, $this->input());
        if ($due) {
            $this->travelTo($record->scheduled_archive_at);
        }

        return $record;
    }

    private function order(array $overrides = []): Order
    {
        return Order::create($overrides + ['buyer_id' => $this->buyer->id, 'status' => 'delivered', 'payment_status' => 'paid', 'total' => 100, 'total_amount' => 100]);
    }

    public function test_web_and_api_share_deadline_status_and_grace_access(): void
    {
        $this->post('/profile/delete-account', $this->input())->assertRedirect(route('buyer.account-deletion'));
        $record = AccountDeletionRequest::firstOrFail();
        $this->assertTrue($record->requested_at->copy()->addDays(30)->equalTo($record->scheduled_archive_at));
        $this->getJson('/api/account-deletion')->assertOk()->assertJsonPath('deletion_request.status', 'pending')->assertJsonPath('deletion_request.remaining_days', 30);
        $this->get('/profile/account-deletion')->assertOk()->assertSee('Account Deletion Scheduled');
        $this->assertTrue($this->buyer->fresh()->isActive());
        $this->getJson('/api/orders')->assertOk();
    }

    public function test_reason_confirmation_and_password_are_required(): void
    {
        foreach (['reason', 'confirm_deletion', 'current_password'] as $field) {
            $input = $this->input();
            unset($input[$field]);
            $this->postJson('/api/account-deletion', $input)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('account_deletion_requests', 0);
    }

    public function test_other_requires_nonempty_explanation(): void
    {
        $this->postJson('/api/account-deletion', $this->input(['reason' => 'Other (please specify).', 'additional_feedback' => ' ']))->assertUnprocessable()->assertJsonValidationErrors('additional_feedback');
        $this->postJson('/api/account-deletion', $this->input(['reason' => 'Other (please specify).', 'additional_feedback' => 'A specific reason.']))->assertCreated();
    }

    public function test_reauthentication_rejects_invalid_password_and_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/account-deletion/verify', ['current_password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/account-deletion/verify', ['current_password' => 'wrong'])->assertStatus(429);
        $this->assertDatabaseCount('account_deletion_requests', 0);
    }

    public function test_direct_submission_also_verifies_current_password(): void
    {
        $this->postJson('/api/account-deletion', $this->input(['current_password' => 'wrong']))->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }

    public function test_duplicate_request_preserves_original_deadline(): void
    {
        $record = $this->submitted();
        $deadline = $record->scheduled_archive_at->toIso8601String();
        $this->travel(5)->days();
        $this->postJson('/api/account-deletion', $this->input())->assertUnprocessable();
        $this->assertDatabaseCount('account_deletion_requests', 1);
        $this->assertSame($deadline, $record->fresh()->scheduled_archive_at->toIso8601String());
        $this->assertSame('pending', $record->fresh()->status);
    }

    public function test_cancelled_requests_keep_history_and_are_not_archived(): void
    {
        $record = $this->submitted();
        $this->postJson('/api/account-deletion/cancel', ['confirm_cancellation' => true])->assertOk()->assertJsonPath('deletion_request.status', 'cancelled');
        $this->travel(31)->days();
        $this->service->process($record->id);
        $this->assertTrue($this->buyer->fresh()->isActive());
        $this->assertNotNull($record->fresh()->cancelled_at);
        $this->assertSame(2, AuditLog::count());
    }

    public function test_cancellation_requires_confirmation_and_stops_at_deadline(): void
    {
        $record = $this->submitted();
        $this->postJson('/api/account-deletion/cancel', [])->assertUnprocessable();
        $this->travelTo($record->scheduled_archive_at);
        $this->postJson('/api/account-deletion/cancel', ['confirm_cancellation' => true])->assertConflict();
    }

    public function test_archive_starts_at_exact_deadline_and_is_idempotent(): void
    {
        $record = $this->submitted();
        $this->travelTo($record->scheduled_archive_at->copy()->subSecond());
        $this->service->process($record->id);
        $this->assertTrue($this->buyer->fresh()->isActive());
        $this->travelTo($record->scheduled_archive_at);
        $this->service->process($record->id);
        $this->service->process($record->id);
        $this->assertSame('completed', $record->fresh()->status);
        $this->assertNotNull($record->fresh()->archived_at);
        $this->assertFalse($this->buyer->fresh()->isActive());
        $this->assertDatabaseHas('users', ['id' => $this->buyer->id, 'email' => $this->buyer->email]);
        $this->assertSame(1, AuditLog::where('action', 'buyer_deletion_completed')->count());
    }

    public function test_pending_order_holds_then_resumes_after_resolution(): void
    {
        $order = $this->order(['status' => 'processing']);
        $record = $this->submitted(true);
        $this->service->process($record->id);
        $this->assertSame('on_hold', $record->fresh()->status);
        $this->assertTrue($this->buyer->fresh()->isActive());
        $this->assertSame('processing', $order->fresh()->status);
        $this->postJson('/api/account-deletion', $this->input())->assertUnprocessable();
        $order->update(['status' => 'delivered']);
        $this->service->process($record->id);
        $this->assertSame('completed', $record->fresh()->status);
    }

    public function test_pending_delivery_blocks_archive(): void
    {
        $order = $this->order();
        DB::table('deliveries')->insert(['order_id' => $order->id, 'status' => 'out_for_delivery']);
        $record = $this->submitted(true);
        $this->service->process($record->id);
        $this->assertSame('on_hold', $record->fresh()->status);
        $this->assertDatabaseHas('deliveries', ['status' => 'out_for_delivery']);
    }

    public function test_refunds_and_disputes_hold_then_resume(): void
    {
        DB::table('return_requests')->insert(['buyer_id' => $this->buyer->id, 'status' => 'approved']);
        DB::table('complaints')->insert(['buyer_id' => $this->buyer->id, 'status' => 'open']);
        $record = $this->submitted(true);
        $this->service->process($record->id);
        $this->assertSame('on_hold', $record->fresh()->status);
        $this->assertStringContainsString('Refund', $record->fresh()->hold_reason);
        $this->assertStringContainsString('Disputes', $record->fresh()->hold_reason);
        DB::table('return_requests')->update(['status' => 'refunded']);
        DB::table('complaints')->update(['status' => 'resolved']);
        $this->service->process($record->id);
        $this->assertSame('completed', $record->fresh()->status);
    }

    public function test_unsettled_financial_transactions_block_archive(): void
    {
        $order = $this->order(['payment_status' => 'unpaid']);
        Payment::create(['order_id' => $order->id, 'method' => 'gcash', 'status' => 'pending', 'amount' => 100]);
        DB::table('payment_transactions')->insert(['order_id' => $order->id, 'status' => 'pending', 'amount' => 100]);
        $record = $this->submitted(true);
        $this->service->process($record->id);
        $this->assertSame('on_hold', $record->fresh()->status);
        $order->update(['payment_status' => 'paid']);
        Payment::query()->update(['status' => 'paid']);
        $this->service->process($record->id);
        $this->assertSame('on_hold', $record->fresh()->status);
        DB::table('payment_transactions')->update(['status' => 'successful']);
        $this->service->process($record->id);
        $this->assertSame('completed', $record->fresh()->status);
    }

    public function test_archive_revokes_tokens_sessions_and_blocks_web_and_mobile(): void
    {
        $token = $this->buyer->createToken('test')->plainTextToken;
        DB::table('sessions')->insert(['id' => 'active-session', 'user_id' => $this->buyer->id]);
        $record = $this->submitted(true);
        $this->service->process($record->id);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('sessions', 0);
        auth()->guard('web')->logout();
        $this->withToken($token)->getJson('/api/orders')->assertUnauthorized();
        $this->postJson('/api/mobile/buyer/login', ['email' => $this->buyer->email, 'password' => 'Password123!'])->assertForbidden();
        $this->post('/login', ['email' => $this->buyer->email, 'password' => 'Password123!'])->assertSessionHasErrors('email');
        $this->actingAs($this->buyer->fresh())->getJson('/api/orders')->assertForbidden();
        $this->get('/orders')->assertRedirect('/login');
    }

    public function test_legacy_settlements_and_escrow_must_be_resolved_before_archiving(): void
    {
        foreach (['escrow_remittances', 'remittances', 'transactions'] as $table) {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->unsignedBigInteger('order_id')->nullable();
                $blueprint->unsignedBigInteger('delivery_id')->nullable();
                $blueprint->decimal('amount', 12, 2);
                $blueprint->string('status');
            });
        }
        $order = $this->order();
        $deliveryId = DB::table('deliveries')->insertGetId(['order_id' => $order->id, 'status' => 'delivered']);
        DB::table('escrow_remittances')->insert(['order_id' => $order->id, 'amount' => 90, 'status' => 'pending_remittance']);
        DB::table('remittances')->insert(['order_id' => $order->id, 'amount' => 90, 'status' => 'pending']);
        DB::table('transactions')->insert(['delivery_id' => $deliveryId, 'amount' => 100, 'status' => 'pending']);
        $record = $this->submitted(true);
        $this->service->process($record->id);
        $this->assertSame('on_hold', $record->fresh()->status);
        DB::table('escrow_remittances')->update(['status' => 'remitted']);
        DB::table('remittances')->update(['status' => 'confirmed']);
        $this->service->process($record->id);
        $this->assertSame('on_hold', $record->fresh()->status);
        DB::table('transactions')->update(['status' => 'completed']);
        $this->service->process($record->id);
        $this->assertSame('completed', $record->fresh()->status);
    }

    public function test_admin_restoration_requires_password_and_preserves_transactions(): void
    {
        $order = $this->order();
        $payment = Payment::create(['order_id' => $order->id, 'method' => 'gcash', 'status' => 'paid', 'amount' => 100]);
        $record = $this->submitted(true);
        $this->service->process($record->id);
        $admin = $this->account(['role' => 'admin', 'is_admin' => true]);
        $body = ['current_password' => 'Password123!', 'identity_verified' => true, 'confirm_restore' => true];
        $this->actingAs($this->account())->postJson('/api/admin/account-deletions/'.$record->id.'/restore', $body)->assertForbidden();
        $this->actingAs($admin)->postJson('/api/admin/account-deletions/'.$record->id.'/restore', ['current_password' => 'wrong'] + $body)->assertUnprocessable();
        $this->postJson('/api/admin/account-deletions/'.$record->id.'/restore', ['current_password' => 'Password123!'])->assertUnprocessable();
        $this->postJson('/api/admin/account-deletions/'.$record->id.'/restore', $body)->assertOk();
        $this->assertTrue($this->buyer->fresh()->isActive());
        $this->assertSame('completed', $record->fresh()->status);
        $this->assertSame($admin->id, $record->fresh()->restored_by);
        $this->assertNotNull($record->fresh()->restored_at);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('delivered', $order->fresh()->status);
        $this->postJson('/api/admin/account-deletions/'.$record->id.'/restore', $body)->assertConflict();
    }

    public function test_unrelated_suspension_and_normal_activation_cannot_bypass_restoration(): void
    {
        $record = $this->submitted(true);
        $this->service->process($record->id);
        $this->actingAs($this->account(['role' => 'admin', 'is_admin' => true]))->post('/admin/manage-accounts/'.$this->buyer->id.'/action', ['action' => 'activate'])->assertForbidden();
        DB::table('users')->where('id', $this->buyer->id)->update(['account_status' => 'suspended', 'status' => 'suspended']);
        $this->postJson('/api/admin/account-deletions/'.$record->id.'/restore', ['current_password' => 'Password123!', 'identity_verified' => true, 'confirm_restore' => true])->assertConflict();
        $this->assertFalse($this->buyer->fresh()->isActive());
    }

    public function test_existing_restrictions_hold_instead_of_being_overwritten(): void
    {
        $record = $this->submitted(true);
        $this->buyer->update(['status' => 'suspended']);
        $this->service->process($record->id);
        $this->assertSame('on_hold', $record->fresh()->status);
        $this->assertNull($this->buyer->fresh()->archived_at);
    }

    public function test_other_roles_cannot_submit_or_cancel(): void
    {
        foreach (['seller', 'rider', 'logistics_staff', 'admin'] as $role) {
            $this->actingAs($this->account(['role' => $role]))->postJson('/api/account-deletion', $this->input())->assertForbidden();
            $this->postJson('/api/account-deletion/cancel', ['confirm_cancellation' => true])->assertForbidden();
        }
        $this->actingAs($this->account(['is_admin' => true]))->postJson('/api/account-deletion', $this->input())->assertForbidden();
        $this->assertDatabaseCount('account_deletion_requests', 0);
    }

    public function test_scheduler_reminders_and_holds_do_not_duplicate_notifications(): void
    {
        $record = $this->submitted();
        $this->travel(23)->days();
        $this->artisan('buyers:process-account-deletions')->assertSuccessful();
        $this->artisan('buyers:process-account-deletions')->assertSuccessful();
        $this->assertSame(1, AuditLog::where('action', 'buyer_deletion_reminder')->count());
        $this->order(['status' => 'pending']);
        $this->travel(7)->days();
        $this->artisan('buyers:process-account-deletions')->assertSuccessful();
        $this->artisan('buyers:process-account-deletions')->assertSuccessful();
        $this->assertSame(1, AuditLog::where('action', 'buyer_deletion_on_hold')->count());
        $this->assertSame('on_hold', $record->fresh()->status);
    }

    public function test_admin_search_and_details_are_authorized(): void
    {
        $record = $this->submitted();
        $this->actingAs($this->account(['role' => 'admin', 'is_admin' => true]));
        $this->getJson('/api/admin/account-deletions?status=pending&search='.$record->id)->assertOk()->assertJsonPath('data.0.id', $record->id);
        $this->get('/admin/account-deletions/'.$record->id)->assertOk()->assertSee($record->reason)->assertSee('Audit history');
        $this->get('/admin/account-deletions')->assertOk()->assertSee('Account Deletion Requests');
        $this->actingAs($this->buyer)->getJson('/api/admin/account-deletions')->assertForbidden();
    }
}
