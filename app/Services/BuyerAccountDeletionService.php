<?php

namespace App\Services;

use App\Models\AccountDeletionRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountDeletionNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BuyerAccountDeletionService
{
    public const REASONS = [
        'I no longer use INVOIZ.', 'I have another account.', 'I am concerned about my privacy.',
        'I am not satisfied with the services.', 'I experienced problems with orders or deliveries.',
        'I experienced problems with the application or website.', 'I receive too many notifications.', 'Other (please specify).',
    ];

    public const MESSAGES = [
        'submitted' => 'Your account deletion is scheduled in 30 days. You can cancel before the scheduled date.',
        'cancelled' => 'Your account deletion request was cancelled. Your account remains active.',
        'reminder' => 'Your account deletion date is approaching within 7 days. You can cancel before the scheduled date.',
        'on_hold' => 'Account deletion is on hold while outstanding transactions or account restrictions are resolved.',
        'completed' => 'Your INVOIZ account has been deactivated and archived. Contact support to request administrator restoration.',
        'restored' => 'An administrator restored your INVOIZ account. You can sign in again.',
    ];

    public function assertBuyer(User $user): void
    {
        abort_unless($this->buyerOnly($user), 403, 'This feature is available only to buyer accounts.');
    }

    private function buyerOnly(User $user): bool
    {
        if (! $user->isBuyer() || $user->isAdmin() || $user->seller()->exists()) {
            return false;
        }
        foreach (['riders', 'hub_transport_drivers'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id') && DB::table($table)->where('user_id', $user->id)->exists()) {
                return false;
            }
        }

        return true;
    }

    public function verifyPassword(User $user, string $password): void
    {
        if (! $user->getAuthPassword() || ! Hash::check($password, $user->getAuthPassword())) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect. Reauthenticate before continuing.']);
        }
    }

    public function active(User $user): ?AccountDeletionRequest
    {
        return $user->accountDeletionRequests()->whereIn('status', ['pending', 'on_hold'])->latest('id')->first();
    }

    public function submit(User $user, array $data): AccountDeletionRequest
    {
        return DB::transaction(function () use ($user, $data) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assertBuyer($user);
            abort_unless($user->isActive(), 403);
            $this->verifyPassword($user, $data['current_password']);
            if ($this->active($user)) {
                throw ValidationException::withMessages(['reason' => 'An account deletion request is already active. Its original deadline is unchanged.']);
            }
            $submittedAt = now();
            $request = $user->accountDeletionRequests()->create([
                'reason' => $data['reason'], 'additional_feedback' => $data['additional_feedback'] ?? null,
                'requested_at' => $submittedAt, 'scheduled_archive_at' => $submittedAt->copy()->addDays(30), 'status' => 'pending',
            ]);
            $this->event($request, 'submitted', $user);

            return $request;
        });
    }

    public function cancel(User $user): AccountDeletionRequest
    {
        return DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assertBuyer($user);
            $request = $this->active($user);
            abort_unless($user->isActive() && $request && now()->lt($request->scheduled_archive_at), 409, 'The cancellation period has ended or no active request exists.');
            $request->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $this->event($request, 'cancelled', $user);

            return $request;
        });
    }

    public function blockers(User $user): array
    {
        $blockers = [];
        $orders = fn () => DB::table('orders')->where('buyer_id', $user->id);
        if ($orders()->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['delivered', 'completed', 'cancelled', 'refunded', 'returned']))->exists()) {
            $blockers[] = 'Orders are still being processed or shipped.';
        }
        if (DB::table('deliveries')->whereIn('order_id', $orders()->select('id'))
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['delivered', 'cancelled', 'returned_to_seller']))->exists()) {
            $blockers[] = 'Deliveries have not been completed.';
        }
        foreach (['return_requests' => 'Refund or return requests are unresolved.', 'complaints' => 'Disputes or complaints are unresolved.'] as $table => $message) {
            if (DB::table($table)->where('buyer_id', $user->id)->where(function ($query) {
                $query->whereNull('status')->orWhereNotIn('status', ['completed', 'resolved', 'rejected', 'cancelled', 'refunded', 'closed']);
            })->exists()) {
                $blockers[] = $message;
            }
        }
        $payable = fn () => $orders()->whereNotIn('status', ['cancelled', 'refunded']);
        if ($payable()->whereRaw('COALESCE(total_amount, total, 0) > 0')->where(function ($query) {
            $query->whereNull('payment_status')->orWhereNotIn('payment_status', ['paid', 'refunded']);
        })->exists()) {
            $blockers[] = 'Order payments are not settled.';
        }
        foreach (['payments' => ['paid', 'refunded', 'cancelled', 'voided'], 'payment_transactions' => ['successful', 'refunded', 'cancelled', 'voided']] as $table => $settled) {
            if (DB::table($table)->whereIn('order_id', $orders()->select('id'))->where('amount', '>', 0)
                ->where(fn ($query) => $query->whereNull('status')->orWhereNotIn('status', $settled))->exists()) {
                $blockers[] = 'Payment or financial transactions remain unresolved.';
            }
        }
        foreach ([
            'escrow_remittances' => ['remitted', 'accepted', 'voided', 'cancelled'],
            'remittances' => ['confirmed', 'remitted', 'accepted', 'voided', 'cancelled', 'completed', 'paid', 'refunded'],
        ] as $table => $settled) {
            if (Schema::hasTable($table) && DB::table($table)->whereIn('order_id', $orders()->select('id'))->where('amount', '>', 0)
                ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', $settled))->exists()) {
                $blockers[] = 'Order remittances or escrow allocations remain unsettled.';
            }
        }
        if (Schema::hasTable('transactions') && DB::table('transactions')
            ->whereIn('delivery_id', DB::table('deliveries')->whereIn('order_id', $orders()->select('id'))->select('id'))
            ->where('amount', '>', 0)->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['completed', 'voided', 'cancelled', 'refunded']))->exists()) {
            $blockers[] = 'Delivery payment settlement is unresolved.';
        }

        return array_values(array_unique($blockers));
    }

    public function process(int $id): void
    {
        $record = AccountDeletionRequest::findOrFail($id);
        DB::transaction(function () use ($record) {
            $user = User::whereKey($record->user_id)->lockForUpdate()->firstOrFail();
            $record = AccountDeletionRequest::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if (! in_array($record->status, ['pending', 'on_hold'], true)) {
                return;
            }
            if (now()->lt($record->scheduled_archive_at)) {
                if (! $record->reminder_sent_at && now()->gte($record->scheduled_archive_at->copy()->subDays(7))) {
                    $record->update(['reminder_sent_at' => now()]);
                    $this->event($record, 'reminder', $user);
                }

                return;
            }
            $blockers = $this->blockers($user);
            if (! $this->buyerOnly($user) || ! $user->isActive() || $user->status !== 'active' || $user->account_status !== 'active') {
                $blockers[] = 'Account role or existing restrictions require administrator review.';
            }
            if ($blockers) {
                $reason = implode(' ', $blockers);
                if ($record->status !== 'on_hold' || $record->hold_reason !== $reason) {
                    $record->update(['status' => 'on_hold', 'hold_reason' => $reason]);
                    $this->event($record, 'on_hold', $user);
                }

                return;
            }
            $record->update([
                'status' => 'completed', 'archived_at' => now(), 'hold_reason' => null,
                'previous_status' => $user->status, 'previous_account_status' => $user->account_status,
            ]);
            $user->archived_at = $record->archived_at;
            $user->account_status = 'deactivated';
            $user->status = 'inactive';
            $user->remember_token = Str::random(60);
            $user->save();
            $user->tokens()->delete();
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            }
            $this->event($record, 'completed', $user);
        });
    }

    public function restore(AccountDeletionRequest $record, User $admin, string $password): void
    {
        DB::transaction(function () use ($record, $admin, $password) {
            $admin = User::whereKey($admin->id)->lockForUpdate()->firstOrFail();
            abort_unless($admin->isPlatformAdministrator() && $admin->isActive(), 403);
            $this->verifyPassword($admin, $password);
            $user = User::whereKey($record->user_id)->lockForUpdate()->firstOrFail();
            $record = AccountDeletionRequest::whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'completed' && ! $record->restored_at && $user->archived_at
                && $record->archived_at && $user->archived_at->equalTo($record->archived_at)
                && $this->buyerOnly($user)
                && $user->status === 'inactive' && $user->account_status === 'deactivated'
                && $record->previous_status === 'active' && $record->previous_account_status === 'active'
                && ! $user->hasBlockedMarketplaceApproval(), 409, 'This account cannot be restored while other restrictions apply.');
            $user->archived_at = null;
            $user->status = 'active';
            $user->account_status = 'active';
            $user->save();
            $record->update(['restored_at' => now(), 'restored_by' => $admin->id]);
            $this->event($record, 'restored', $admin);
        });
    }

    public function payload(?AccountDeletionRequest $request): ?array
    {
        if (! $request) {
            return null;
        }

        return $request->only(['id', 'reason', 'additional_feedback', 'status', 'hold_reason']) + [
            'requested_at' => $request->requested_at->toIso8601String(),
            'scheduled_archive_at' => $request->scheduled_archive_at->toIso8601String(),
            'cancelled_at' => $request->cancelled_at?->toIso8601String(),
            'archived_at' => $request->archived_at?->toIso8601String(),
            'restored_at' => $request->restored_at?->toIso8601String(),
            'remaining_days' => max(0, (int) ceil(now()->diffInSeconds($request->scheduled_archive_at, false) / 86400)),
            'can_cancel' => in_array($request->status, ['pending', 'on_hold'], true) && now()->lt($request->scheduled_archive_at),
        ];
    }

    private function event(AccountDeletionRequest $request, string $action, User $actor): void
    {
        $system = in_array($action, ['reminder', 'on_hold', 'completed'], true);
        AuditLog::create(['user_id' => $system ? null : $actor->id, 'action' => 'buyer_deletion_'.$action,
            'entity' => 'account_deletion_requests', 'entity_id' => $request->id,
            'meta' => ['buyer_id' => $request->user_id, 'message' => self::MESSAGES[$action]]
                + ($action === 'on_hold' ? ['hold_reason' => $request->hold_reason] : [])
                + ($action === 'restored' ? ['identity_verified' => true] : [])]);
        DB::afterCommit(function () use ($request, $action) {
            try {
                $request->user->notify(new AccountDeletionNotice(self::MESSAGES[$action]));
            } catch (\Throwable $error) {
                report($error);
            }
        });
    }
}
