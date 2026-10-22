<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('deliveries', 'cod_amount_received')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->decimal('cod_amount_received', 12, 2)->nullable()->after('amount_to_collect');
            });
        }

        if (! Schema::hasColumn('deliveries', 'cod_received_at')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->timestamp('cod_received_at')->nullable()->after('cod_amount_received');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('deliveries', 'cod_received_at')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->dropColumn('cod_received_at');
            });
        }

        if (Schema::hasColumn('deliveries', 'cod_amount_received')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->dropColumn('cod_amount_received');
            });
        }
    }
};
