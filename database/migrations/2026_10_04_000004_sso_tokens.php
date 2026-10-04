<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-use cross-app login tokens (shared invoizdb).
 * Lets an authenticated user jump from the integrated app (:8000) into the
 * exact seller app (:8100) without sharing session formats.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sso_tokens')) {
            Schema::create('sso_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('token', 128)->unique();
                $table->unsignedBigInteger('user_id');
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
                $table->index('user_id');
            });
        }
    }

    public function down(): void {}
};
