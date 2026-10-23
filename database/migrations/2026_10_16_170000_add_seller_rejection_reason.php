<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sellers', 'rejection_reason')) {
            Schema::table('sellers', function (Blueprint $table) {
                Schema::hasColumn('sellers', 'approval_status')
                    ? $table->text('rejection_reason')->nullable()->after('approval_status')
                    : $table->text('rejection_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sellers', 'rejection_reason')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};
