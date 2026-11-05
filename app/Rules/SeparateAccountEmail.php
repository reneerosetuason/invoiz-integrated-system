<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Marketplace identities and logistics identities must use different emails. */
class SeparateAccountEmail implements ValidationRule
{
    public function __construct(private string $family, private ?int $ignoreUserId = null) {}

    public static function marketplaceExists(string $email, ?int $ignoreUserId = null): bool
    {
        $users = DB::table('users')->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($email))])
            ->when($ignoreUserId, fn ($q, $id) => $q->where('id', '!=', $id));

        return $users->where(function ($q) {
            $q->whereIn('role', ['buyer', 'seller']);
            if (Schema::hasTable('sellers')) {
                $q->orWhereExists(fn ($seller) => $seller->selectRaw('1')->from('sellers')->whereColumn('sellers.user_id', 'users.id'));
            }
        })->exists();
    }

    public static function logisticsExists(string $email, ?int $ignoreUserId = null): bool
    {
        $email = strtolower(trim($email));
        if (DB::table('users')->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->when($ignoreUserId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->whereNotIn('role', ['buyer', 'seller'])->exists()) {
            return true;
        }
        foreach (['riders', 'hub_transport_drivers', 'rider_applications', 'logistics_center_applications'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $query = DB::table($table)->whereRaw('LOWER(TRIM(email)) = ?', [$email]);
            if (in_array($table, ['rider_applications', 'logistics_center_applications'], true)) {
                $query->where('status', '!=', 'rejected');
            }
            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }
        if ($this->family === 'marketplace' && self::logisticsExists($value, $this->ignoreUserId)) {
            $fail('This email is already used by a Rider or Logistics account or application. Use a different email for a buyer or seller account.');
        } elseif ($this->family === 'logistics' && self::marketplaceExists($value, $this->ignoreUserId)) {
            $fail('This email is already used by a Buyer or Seller account. Use a different email for a Rider or Logistics account.');
        }
    }
}
