<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store vacation mode: a seller can pause their store. While on vacation
 * buyers see a notice and cannot place new orders from them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sellers')) {
            return;
        }

        Schema::table('sellers', function (Blueprint $table) {
            if (! Schema::hasColumn('sellers', 'vacation_mode')) {
                $table->boolean('vacation_mode')->default(false)->after('status');
            }
            if (! Schema::hasColumn('sellers', 'vacation_message')) {
                $table->string('vacation_message', 255)->nullable()->after('vacation_mode');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sellers')) {
            return;
        }

        Schema::table('sellers', function (Blueprint $table) {
            if (Schema::hasColumn('sellers', 'vacation_mode')) {
                $table->dropColumn('vacation_mode');
            }
            if (Schema::hasColumn('sellers', 'vacation_message')) {
                $table->dropColumn('vacation_message');
            }
        });
    }
};
