<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Logistics back-office accounts.
 *
 * These roles exist only in the users.role enum added by
 * 2026_10_11_142249_add_logistics_roles_to_users and are the accounts the
 * /logistics login (App\Http\Requests\Auth\LoginRequest) and the
 * Logistics\EnsureAdmin / Logistics\EnsureLogisticsStaff middleware expect.
 *
 * is_admin stays FALSE on purpose: LoginRequest rejects any account with
 * is_admin truthy from the logistics form ("Administrator accounts must use
 * the main admin login page"), so the marketplace admin flag must not be set
 * here even for logistics_admin.
 */
class LogisticsUserSeeder extends Seeder
{
    public function run(): void
    {
        $isLocal = app()->environment(['local', 'testing']);

        $password = env('LOGISTICS_SEED_PASSWORD');
        $adminEmail = env('LOGISTICS_ADMIN_SEED_EMAIL');
        $staffEmail = env('LOGISTICS_STAFF_SEED_EMAIL');
        $staffCebuEmail = env('LOGISTICS_STAFF_CEBU_SEED_EMAIL');

        if (! $isLocal) {
            $missing = [];

            if (! $password) {
                $missing[] = 'LOGISTICS_SEED_PASSWORD';
            }
            if (! $adminEmail) {
                $missing[] = 'LOGISTICS_ADMIN_SEED_EMAIL';
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
            $adminEmail ??= 'logistics_admin@test.com';
            $staffEmail ??= 'logistics_staff@test.com';
            $staffCebuEmail ??= 'logistics_staff_cebu@test.com';
        }

        $centers = DB::table('logistics_centers')->pluck('id', 'name');

        $accounts = [
            [
                'email' => $adminEmail,
                'first_name' => 'Logistics',
                'last_name' => 'Admin',
                'role' => 'logistics_admin',
                'center' => null,
                'phone' => '09170000001',
            ],
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
