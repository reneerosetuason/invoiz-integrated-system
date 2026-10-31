<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Logistics back-office accounts.
 *
 * These roles exist only in the users.role enum added by
 * 2026_10_11_142249_add_logistics_roles_to_users and are the accounts the
 * /logistics login and staff middleware expect.
 *
 * The designated global Logistics Admin is never created or updated here.
 * Its credentials and identity are provisioned separately and remain intact
 * when this demo seeder is rerun.
 */
class LogisticsUserSeeder extends Seeder
{
    public function run(): void
    {
        $isLocal = app()->environment(['local', 'testing']);

        $password = env('LOGISTICS_SEED_PASSWORD');
        $staffEmail = env('LOGISTICS_STAFF_SEED_EMAIL');
        $staffCebuEmail = env('LOGISTICS_STAFF_CEBU_SEED_EMAIL');

        if (! $isLocal) {
            $missing = [];

            if (! $password) {
                $missing[] = 'LOGISTICS_SEED_PASSWORD';
            }
            if (! $staffEmail) {
                $missing[] = 'LOGISTICS_STAFF_SEED_EMAIL';
            }
            if (! $staffCebuEmail) {
                $missing[] = 'LOGISTICS_STAFF_CEBU_SEED_EMAIL';
            }

            if ($missing !== []) {
                $this->command?->warn('LogisticsUserSeeder skipped: missing required production values: '.implode(', ', $missing).'.');

                return;
            }
        }

        if ($isLocal) {
            $password ??= 'password123';
            $staffEmail ??= 'logistics_staff@test.com';
            $staffCebuEmail ??= 'logistics_staff_cebu@test.com';
        }

        $designatedAdmin = DB::table('users')
            ->where('id', User::GLOBAL_LOGISTICS_ADMIN_ID)
            ->first(['id', 'email', 'role', 'is_admin', 'status', 'account_status']);

        if (! $designatedAdmin) {
            $this->command?->warn('The designated global Logistics Admin (user ID 18) is not present; this seeder will not create or replace it.');
        } elseif (
            $designatedAdmin->email !== User::GLOBAL_LOGISTICS_ADMIN_EMAIL
            || $designatedAdmin->role !== 'logistics_admin'
            || (bool) $designatedAdmin->is_admin
            || $designatedAdmin->status !== 'active'
            || (($designatedAdmin->account_status ?? 'active') !== 'active')
        ) {
            throw new \RuntimeException('The designated global Logistics Admin identity or active state does not match the approved account. No account fields were changed.');
        }

        $centers = DB::table('logistics_centers')->pluck('id', 'name');

        $accounts = [
            [
                'email' => $staffEmail,
                'first_name' => 'Logistics',
                'last_name' => 'Staff',
                'role' => 'logistics_staff',
                'center' => 'Invoiz Manila Logistics Center',
                'phone' => '09170000001',
            ],
            [
                'email' => $staffCebuEmail,
                'first_name' => 'Logistics',
                'last_name' => 'Staff Cebu',
                'role' => 'logistics_staff',
                'center' => 'Invoiz Cebu Logistics Center',
                'phone' => '09170000002',
            ],
        ];

        foreach ($accounts as $account) {
            if (
                strcasecmp(trim($account['email']), User::GLOBAL_LOGISTICS_ADMIN_EMAIL) === 0
                || (int) DB::table('users')->where('email', $account['email'])->value('id') === User::GLOBAL_LOGISTICS_ADMIN_ID
            ) {
                throw new \RuntimeException('Logistics staff seed addresses cannot replace the designated global Logistics Admin.');
            }

            // Idempotent: re-running only refreshes these demo rows. Centers
            // are resolved by name so the demo works on a fresh seed (global
            // admin stays unscoped; each staff account is center-scoped).
            DB::table('users')->updateOrInsert(['email' => $account['email']], [
                'first_name' => $account['first_name'],
                'last_name' => $account['last_name'],
                'name' => $account['first_name'].' '.$account['last_name'],
                'sex' => 'other',
                'password' => Hash::make($password),
                'phone' => $account['phone'],
                'birthday' => '1990-01-01',
                'age' => 36,
                'approval_status' => 'approved',
                'role' => $account['role'],
                'center_id' => $account['center'] ? ($centers[$account['center']] ?? null) : null,
                'status' => 'active',
                'account_status' => 'active',
                'is_admin' => false,
                'email_verified_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => now(),
            ]);
        }
    }
}
