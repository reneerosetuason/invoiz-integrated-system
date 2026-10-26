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

        $columns = [
            'returned_at' => 'timestamp',
            'return_reason' => 'string',
            'reattempt_requested_at' => 'timestamp',
            'cod_amount_received' => 'money',
            'cod_received_at' => 'timestamp',
            'delivery_otp' => 'text',
            'delivery_otp_expires_at' => 'timestamp',
            'delivery_otp_attempts' => 'attempts',
            'delivery_otp_consumed_at' => 'timestamp',
        ];

        foreach ($columns as $name => $type) {
            if (Schema::hasColumn('deliveries', $name)) {
                continue;
            }

            Schema::table('deliveries', function (Blueprint $table) use ($name, $type): void {
                match ($type) {
                    'money' => $table->decimal($name, 12, 2)->nullable(),
                    'attempts' => $table->unsignedTinyInteger($name)->default(0),
                    'timestamp' => $table->timestamp($name)->nullable(),
                    'text' => $table->text($name)->nullable(),
                    default => $table->string($name)->nullable(),
                };
            });
        }
    }

    public function down(): void
    {
        // These shared workflow columns may predate this compatibility repair.
        // Retain them on rollback rather than deleting rider or payment data.
    }
};
