<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add rider employment-type and vehicle-ownership fields to support the
     * mobile applicant workflow (full/part-time rider, vehicle ownership).
     * Nullable so existing application rows remain valid.
     */
    public function up(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            $table->enum('rider_type', ['full_time', 'part_time'])->nullable()->after('submitted_via');
            $table->enum('vehicle_ownership', ['own', 'borrowed', 'second_hand', 'financing'])->nullable()->after('rider_type');
        });
    }

    public function down(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            $table->dropColumn(['rider_type', 'vehicle_ownership']);
        });
    }
};
