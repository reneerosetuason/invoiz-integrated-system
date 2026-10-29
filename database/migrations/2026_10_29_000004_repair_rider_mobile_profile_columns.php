<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some imported databases record the earlier logistics migrations
        // without containing these fields. Preserve every existing value.
        foreach (['riders', 'rider_applications'] as $name) {
            if (! Schema::hasTable($name)) {
                throw new RuntimeException("Required rider table [{$name}] is missing.");
            }
        }

        foreach (['license_number', 'vehicle_registration'] as $column) {
            if (! Schema::hasColumn('riders', $column)) {
                Schema::table('riders', function (Blueprint $table) use ($column): void {
                    $table->string($column)->nullable();
                });
            }
        }

        foreach (['riders', 'rider_applications'] as $name) {
            if (! Schema::hasColumn($name, 'license_expiry')) {
                Schema::table($name, function (Blueprint $table): void {
                    $table->date('license_expiry')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('This additive rider repair preserves profile data and cannot be rolled back automatically.');
    }
};
