<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RiderMobileSchemaRepairTest extends TestCase
{
    public function test_repair_adds_missing_fields_and_preserves_existing_profiles_on_retries(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('riders', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('license_number')->nullable();
        });
        Schema::create('rider_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->date('license_expiry')->nullable();
        });
        DB::table('riders')->insert(['id' => 1, 'name' => 'Existing rider', 'license_number' => 'KEEP-123']);
        DB::table('rider_applications')->insert(['id' => 1, 'name' => 'Existing applicant', 'license_expiry' => '2030-01-01']);

        $migration = require database_path('migrations/2026_10_29_000004_repair_rider_mobile_profile_columns.php');
        $migration->up();
        DB::table('riders')->where('id', 1)->update(['vehicle_registration' => 'KEEP-456']);
        $migration->up();

        $this->assertDatabaseHas('riders', ['id' => 1, 'name' => 'Existing rider', 'license_number' => 'KEEP-123', 'vehicle_registration' => 'KEEP-456', 'license_expiry' => null]);
        $this->assertDatabaseHas('rider_applications', ['id' => 1, 'name' => 'Existing applicant', 'license_expiry' => '2030-01-01']);
        $this->assertDatabaseCount('riders', 1);
        $this->assertDatabaseCount('rider_applications', 1);
        foreach (['license_number', 'license_expiry', 'vehicle_registration'] as $column) {
            $this->assertTrue(Schema::hasColumn('riders', $column));
        }
        foreach (Schema::getColumns('riders') as $column) {
            if (in_array($column['name'], ['license_number', 'license_expiry', 'vehicle_registration'], true)) {
                $this->assertTrue($column['nullable']);
            }
        }
    }
}
