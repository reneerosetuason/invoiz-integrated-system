<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver's-license detail the merged rider profile / application flow expects
 * but the marketplace migrations never created.
 *
 * The shared database is additive-only here: `license_number` and
 * `vehicle_registration` already exist on both tables (see
 * 2026_08_25_000002_vehicle_types_and_verification); this adds the missing
 * `license_expiry` date on the application and the provisioned rider, so the
 * value captured on the mobile form can be stored and surfaced on the profile.
 * Every column is nullable and guarded so re-running is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('rider_applications', 'license_expiry')) {
                Schema::hasColumn('rider_applications', 'license_number')
                    ? $table->date('license_expiry')->nullable()->after('license_number')
                    : $table->date('license_expiry')->nullable();
            }
        });

        Schema::table('riders', function (Blueprint $table) {
            if (! Schema::hasColumn('riders', 'license_expiry')) {
                Schema::hasColumn('riders', 'license_number')
                    ? $table->date('license_expiry')->nullable()->after('license_number')
                    : $table->date('license_expiry')->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach (['rider_applications', 'riders'] as $table) {
            if (Schema::hasColumn($table, 'license_expiry')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('license_expiry');
                });
            }
        }
    }
};
