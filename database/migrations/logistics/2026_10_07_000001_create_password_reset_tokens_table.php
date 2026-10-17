<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rider self-service password reset token store.
 *
 * Mirrors database/changes/invoizdb_schema.sql (the test/dev bootstrap
 * source of truth, which already carries this table): email PK, hashed
 * token, nullable timestamp. The password broker (config/auth.php,
 * expire 60min) manages creation/validation/expiry; reset tokens are
 * single-use (deleted on successful reset) and never logged.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded: some environments already carry this table from the
        // canonical schema dump, which predates this migration file.
        if (Schema::hasTable('password_reset_tokens')) {
            return;
        }
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 150)->primary();
            $table->string('token', 255);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
