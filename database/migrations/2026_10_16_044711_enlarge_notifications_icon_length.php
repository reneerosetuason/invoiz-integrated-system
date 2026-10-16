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
        // Emoji icons are multi-byte (up to 4 bytes/char); varchar(10)
        // truncated them and 500'd every notification write (e.g. rider
        // applications could never complete). Raw statement: no dbal needed.
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `notifications` MODIFY `icon` VARCHAR(32) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `notifications` MODIFY `icon` VARCHAR(10) NOT NULL");
    }
};
