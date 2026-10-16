<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('addresses') && ! Schema::hasColumn('addresses', 'region')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->string('region')->nullable()->after('province');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('addresses') && Schema::hasColumn('addresses', 'region')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropColumn('region');
            });
        }
    }
};
