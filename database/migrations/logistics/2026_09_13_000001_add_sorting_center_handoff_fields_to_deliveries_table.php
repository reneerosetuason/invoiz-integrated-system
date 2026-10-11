<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Handoff: pickup rider delivers parcel to sorting center.
            $table->timestamp('sorting_center_handoff_at')->nullable()->after('dispatched_at');
            $table->foreignId('sorting_center_handoff_rider_id')->nullable()->after('sorting_center_handoff_at')->constrained('riders')->nullOnDelete();

            // Pickup: assigned delivery rider collects parcel from sorting center.
            $table->timestamp('sorting_center_pickup_at')->nullable()->after('sorting_center_handoff_rider_id');
            $table->foreignId('sorting_center_pickup_rider_id')->nullable()->after('sorting_center_pickup_at')->constrained('riders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['sorting_center_pickup_rider_id']);
            $table->dropForeign(['sorting_center_handoff_rider_id']);
            $table->dropColumn([
                'sorting_center_handoff_at',
                'sorting_center_handoff_rider_id',
                'sorting_center_pickup_at',
                'sorting_center_pickup_rider_id',
            ]);
        });
    }
};
