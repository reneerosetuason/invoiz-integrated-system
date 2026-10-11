<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('participant_type');
            $table->unsignedBigInteger('participant_id');
            $table->string('participant_name');
            $table->string('subject')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedBigInteger('unread_count')->default(0);
            $table->timestamps();

            $table->index(['participant_type', 'participant_id']);
            $table->index('last_message_at');
        });

        Schema::create('logistics_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('logistics_conversations')->cascadeOnDelete();
            $table->string('sender_type');
            $table->unsignedBigInteger('sender_id');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_messages');
        Schema::dropIfExists('logistics_conversations');
    }
};
