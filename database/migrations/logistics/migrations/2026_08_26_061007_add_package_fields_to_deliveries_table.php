<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('package_type')->nullable()->after('notes');
            $table->string('package_description', 500)->nullable()->after('package_type');
            $table->string('priority', 20)->default('normal')->after('package_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['package_type', 'package_description', 'priority']);
        });
    }
};
