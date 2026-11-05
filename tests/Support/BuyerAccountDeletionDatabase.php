<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

trait BuyerAccountDeletionDatabase
{
    use BuyerMobileDatabase;

    protected function createAccountDeletionDatabase(): void
    {
        $this->createMobileDatabase();
        (require database_path('migrations/2026_11_03_000001_create_account_deletion_requests_table.php'))->up();
        (require database_path('migrations/2026_11_04_130000_complete_buyer_account_deletion_workflow.php'))->up();
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('entity');
            $table->unsignedBigInteger('entity_id');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id');
        });
        Notification::fake();
    }
}
