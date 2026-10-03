<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Second admin compat layer for shared invoizdb.
 * Relaxes NOT NULL columns that admin/seller-center writes don't provide:
 *  - messages.conversation_id (buyer chat uses conversations; admin DMs don't)
 *  - vouchers.name (admin creates vouchers with code only)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'conversation_id')) {
                $table->unsignedBigInteger('conversation_id')->nullable()->change();
            }
        });

        Schema::table('vouchers', function (Blueprint $table) {
            if (Schema::hasColumn('vouchers', 'name')) {
                $table->string('name', 150)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // non-destructive: leave relaxed columns as-is
    }
};
