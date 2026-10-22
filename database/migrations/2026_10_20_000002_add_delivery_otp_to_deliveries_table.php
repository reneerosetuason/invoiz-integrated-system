<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'delivery_otp')) {
                $table->text('delivery_otp')->nullable();
            }
            if (! Schema::hasColumn('deliveries', 'delivery_otp_expires_at')) {
                $table->timestamp('delivery_otp_expires_at')->nullable();
            }
            if (! Schema::hasColumn('deliveries', 'delivery_otp_attempts')) {
                $table->unsignedTinyInteger('delivery_otp_attempts')->default(0);
            }
            if (! Schema::hasColumn('deliveries', 'delivery_otp_consumed_at')) {
                $table->timestamp('delivery_otp_consumed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table) {
            foreach ([
                'delivery_otp',
                'delivery_otp_expires_at',
                'delivery_otp_attempts',
                'delivery_otp_consumed_at',
            ] as $column) {
                if (Schema::hasColumn('deliveries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
