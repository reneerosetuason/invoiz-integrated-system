<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_deletion_requests')) {
            Schema::create('account_deletion_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reason');
                $table->text('additional_feedback')->nullable();
                $table->timestamp('requested_at');
                $table->timestamp('scheduled_archive_at');
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->string('status')->default('pending'); // pending, blocked, cancelled, archived
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
