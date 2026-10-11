<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short-lived email-verification codes for rider applications.
 *
 * Only a hash of the 6-digit OTP is ever stored — never plaintext.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_email_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->index();
            $table->string('otp_hash', 255);
            $table->timestamp('expires_at')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_email_verifications');
    }
};
