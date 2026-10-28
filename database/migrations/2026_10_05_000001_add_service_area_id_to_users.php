<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'service_area_id')) {
                $table->foreignId('service_area_id')->nullable()->after('center_id')->constrained('service_areas')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'service_area_id')) {
                $table->dropForeign(['service_area_id']);
                $table->dropColumn('service_area_id');
            }
        });
    }
};