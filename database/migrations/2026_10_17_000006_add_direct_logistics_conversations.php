<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_conversations', function (Blueprint $table) {
            $table->foreignId('logistics_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direct_thread_key', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('logistics_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('logistics_user_id');
            $table->dropUnique(['direct_thread_key']);
            $table->dropColumn('direct_thread_key');
        });
    }
};
