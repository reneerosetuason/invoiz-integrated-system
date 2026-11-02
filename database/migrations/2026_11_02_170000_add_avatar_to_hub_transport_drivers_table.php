<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds avatar support for Hub Transport Drivers to allow profile photo
     * upload and persistence parallel to delivery riders.
     */
    public function up(): void
    {
        if (Schema::hasTable('hub_transport_drivers') && ! Schema::hasColumn('hub_transport_drivers', 'avatar')) {
            Schema::table('hub_transport_drivers', function (Blueprint $table): void {
                $table->string('avatar')->nullable()->after('license_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('hub_transport_drivers') && Schema::hasColumn('hub_transport_drivers', 'avatar')) {
            Schema::table('hub_transport_drivers', function (Blueprint $table): void {
                $table->dropColumn('avatar');
            });
        }
    }
};
