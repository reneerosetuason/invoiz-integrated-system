<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('rider_applications')) {
            Schema::table('rider_applications', function (Blueprint $table): void {
                if (! Schema::hasColumn('rider_applications', 'application_type')) {
                    $table->string('application_type', 30)->default('delivery_rider')->after('rider_type');
                    $table->index(['application_type', 'status'], 'rider_apps_type_status_idx');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('rider_applications')) {
            Schema::table('rider_applications', function (Blueprint $table): void {
                if (Schema::hasColumn('rider_applications', 'application_type')) {
                    $table->dropIndex('rider_apps_type_status_idx');
                    $table->dropColumn('application_type');
                }
            });
        }
    }
};
