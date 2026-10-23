<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Failed-delivery next-step tracking.
 *
 * reattempt_requested_at marks a rider-requested re-attempt on a failed
 * delivery (status stays delivery_failed; Logistics reassigns through the
 * existing assign flow). Cleared on (re)assignment and on each fresh
 * failure. returned_at/return_reason (already on the Delivery model but
 * never migrated) back the return-to-sorting-center action. Positional
 * after() is avoided so the columns can land on any schema vintage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'reattempt_requested_at')) {
                $table->timestamp('reattempt_requested_at')->nullable();
            }
            if (! Schema::hasColumn('deliveries', 'returned_at')) {
                $table->timestamp('returned_at')->nullable();
            }
            if (! Schema::hasColumn('deliveries', 'return_reason')) {
                $table->string('return_reason', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn('reattempt_requested_at');
        });
    }
};
