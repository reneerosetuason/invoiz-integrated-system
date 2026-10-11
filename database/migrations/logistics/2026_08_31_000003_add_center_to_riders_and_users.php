<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->foreignId('center_id')->nullable()->after('id')->constrained('logistics_centers')->nullOnDelete();
            $table->foreignId('service_area_id')->nullable()->after('center_id')->constrained('service_areas')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('center_id')->nullable()->after('role')->constrained('logistics_centers')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending')->after('id_image');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['center_id']);
            $table->dropColumn('center_id');
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->dropForeign(['center_id', 'service_area_id']);
            $table->dropColumn(['center_id', 'service_area_id']);
        });
    }
};
