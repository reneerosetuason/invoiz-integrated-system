<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic per-user preference store (appearance theme, timezone,
     * navigation/sidebar behaviour). Keys are documented on
     * App\Models\LogisticsSetting; the JSON shape keeps new preferences
     * additive without further schema changes.
     */
    public function up(): void
    {
        Schema::table('logistics_settings', function (Blueprint $table) {
            $table->json('preferences')->nullable()->after('delivery');
        });
    }

    public function down(): void
    {
        Schema::table('logistics_settings', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });
    }
};