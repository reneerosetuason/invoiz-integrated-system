<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

trait CenterApplicationDatabase
{
    protected function createCenterApplicationDatabase(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Mail::fake();
        Storage::fake(config('filesystems.default'));
        foreach ([
            'users' => ['name', 'email', 'password', 'role', 'status', 'account_status', 'email_verified_at'],
            'sellers' => ['user_id'],
            'provinces' => ['code', 'name', 'region_code'],
            'municipalities' => ['code', 'province_id', 'name'],
            'barangays' => ['code', 'municipality_id', 'name'],
            'notifications' => ['type', 'title', 'message', 'icon', 'priority', 'audience', 'link'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach ($columns as $column) $table->string($column)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        foreach ([
            '2026_09_06_000001_create_rider_email_verifications_table.php',
            '2026_09_11_000004_create_logistics_center_applications_table.php',
            '2026_09_15_000001_add_wizard_error_steps_to_logistics_center_applications_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        // Equivalent columns for the later MySQL-only ALTER TABLE migrations.
        Schema::table('logistics_center_applications', function (Blueprint $table) {
            foreach (['owner_first_name', 'owner_middle_name', 'owner_last_name', 'sec_number', 'tin', 'postal_code', 'region'] as $column) {
                $table->string($column)->nullable();
            }
        });
    }
}
