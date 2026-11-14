<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** A separately funded platform budget. Buyer escrow is never a subsidy source. */
class LogisticsFundingService
{
    public static function balance(): array
    {
        $account = DB::table('logistics_funding_accounts')->where('id', 1)->first();

        return ['funded' => (int) $account->funded_cents, 'reserved' => (int) $account->reserved_cents,
            'spent' => (int) $account->spent_cents,
            'available' => (int) $account->funded_cents - (int) $account->reserved_cents - (int) $account->spent_cents];
    }

    public static function credit(User $admin, int $cents, string $reference, string $note): void
    {
        abort_unless($admin->isActive() && $admin->isAdmin(), 403);
        DB::transaction(function () use ($admin, $cents, $reference, $note) {
            DB::table('logistics_funding_accounts')->where('id', 1)->lockForUpdate()->first();
            $key = 'FUND:'.strtoupper(trim($reference));
            $old = DB::table('logistics_funding_entries')->where('reference', $key)->first();
            if ($old) {
                abort_unless((int) $old->amount_cents === $cents, 409, 'This funding reference already has a different amount.');

                return;
            }
            abort_unless($cents > 0, 422);
            DB::table('logistics_funding_entries')->insert(['reference' => $key, 'kind' => 'funding', 'amount_cents' => $cents,
                'status' => 'funded', 'actor_id' => $admin->id, 'snapshot' => json_encode(['note' => $note]), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('logistics_funding_accounts')->where('id', 1)->increment('funded_cents', $cents);
        });
    }

    public static function reserve(string $reference, int $cents, ?int $orderId = null, array $snapshot = []): void
    {
        DB::transaction(function () use ($reference, $cents, $orderId, $snapshot) {
            $account = DB::table('logistics_funding_accounts')->where('id', 1)->lockForUpdate()->first();
            $old = DB::table('logistics_funding_entries')->where('reference', $reference)->first();
            if ($old?->status === 'spent') {
                $delta = $cents - (int) $old->amount_cents;
                if ($delta === 0) {
                    return;
                }
                if ($delta < 0 || $delta > (int) $account->funded_cents - (int) $account->reserved_cents - (int) $account->spent_cents) {
                    throw ValidationException::withMessages(['shipping' => 'A spent subsidy needs funded reconciliation before the refund can proceed.']);
                }
                self::audit($reference, (int) $old->amount_cents, $cents, 'spent subsidy increased for funded refund reconciliation', $orderId);
                DB::table('logistics_funding_accounts')->where('id', 1)->increment('spent_cents', $delta);
                DB::table('logistics_funding_entries')->where('id', $old->id)->update(['amount_cents' => $cents, 'snapshot' => json_encode($snapshot), 'updated_at' => now()]);

                return;
            }
            $previous = $old?->status === 'reserved' ? (int) $old->amount_cents : 0;
            $difference = $cents - $previous;
            if ($cents < 0 || $difference > (int) $account->funded_cents - (int) $account->reserved_cents - (int) $account->spent_cents) {
                throw ValidationException::withMessages(['shipping' => 'The platform shipping subsidy is not funded. Fund the budget or consolidate the trip before proceeding.']);
            }
            if ($old && $previous !== $cents) {
                self::audit($reference, $previous, $cents, 'subsidy reservation adjusted', $orderId);
            }
            DB::table('logistics_funding_entries')->updateOrInsert(['reference' => $reference], [
                'kind' => 'subsidy', 'amount_cents' => $cents, 'status' => $cents ? 'reserved' : 'voided',
                'order_id' => $orderId, 'snapshot' => json_encode($snapshot), 'created_at' => $old?->created_at ?? now(), 'updated_at' => now()]);
            DB::table('logistics_funding_accounts')->where('id', 1)->increment('reserved_cents', $difference);
        });
    }

    public static function finish(string $reference, bool $spent): void
    {
        DB::transaction(function () use ($reference, $spent) {
            DB::table('logistics_funding_accounts')->where('id', 1)->lockForUpdate()->first();
            $entry = DB::table('logistics_funding_entries')->where('reference', $reference)->first();
            if (! $entry || $entry->status !== 'reserved') {
                return;
            }
            self::audit($reference, (int) $entry->amount_cents, $spent ? (int) $entry->amount_cents : 0, $spent ? 'subsidy incurred' : 'unused subsidy released', $entry->order_id);
            DB::table('logistics_funding_accounts')->where('id', 1)->decrement('reserved_cents', $entry->amount_cents);
            if ($spent) {
                DB::table('logistics_funding_accounts')->where('id', 1)->increment('spent_cents', $entry->amount_cents);
            }
            DB::table('logistics_funding_entries')->where('id', $entry->id)->update(['status' => $spent ? 'spent' : 'voided', 'updated_at' => now()]);
        });
    }

    private static function audit(string $source, int $before, int $after, string $reason, ?int $orderId): void
    {
        DB::table('logistics_funding_entries')->insert(['reference' => 'AUDIT:'.Str::uuid(), 'kind' => 'adjustment',
            'amount_cents' => abs($after - $before), 'status' => 'recorded', 'order_id' => $orderId,
            'snapshot' => json_encode(['source' => $source, 'before_cents' => $before, 'after_cents' => $after, 'reason' => $reason]),
            'created_at' => now(), 'updated_at' => now()]);
    }

    public static function committed(string $reference): int
    {
        return (int) DB::table('logistics_funding_entries')->where('reference', $reference)->whereIn('status', ['reserved', 'spent'])->value('amount_cents');
    }
}
