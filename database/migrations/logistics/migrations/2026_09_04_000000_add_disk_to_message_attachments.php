<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_message_attachments', function (Blueprint $table) {
            $table->string('disk', 20)->default('local')->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('logistics_message_attachments', function (Blueprint $table) {
            $table->dropColumn('disk');
        });
    }
};
