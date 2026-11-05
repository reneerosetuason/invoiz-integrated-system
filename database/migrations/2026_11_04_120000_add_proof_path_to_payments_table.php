<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'proof_path')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->string('proof_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Payment receipts must be retained; automatic rollback is disabled.');
    }
};
