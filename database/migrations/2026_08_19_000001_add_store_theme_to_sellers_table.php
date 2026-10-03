<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('primary_color', 9)->default('#16697A')->after('status');
            $table->string('accent_color', 9)->default('#F0A202')->after('primary_color');
            $table->string('logo')->nullable()->after('accent_color');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'accent_color', 'logo']);
        });
    }
};