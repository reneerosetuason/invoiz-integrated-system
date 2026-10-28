<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_center_applications', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('owner_name');
            $table->string('email');
            $table->string('phone');
            $table->string('house_number')->nullable();
            $table->string('street')->nullable();
            $table->string('barangay')->nullable();
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->text('address');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('submitted_via')->default('mobile');
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('status');
        });

        Schema::create('logistics_center_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_center_application_id');
            $table->foreign('logistics_center_application_id', 'lca_docs_application_id_foreign')
                ->references('id')->on('logistics_center_applications')
                ->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_center_application_documents');
        Schema::dropIfExists('logistics_center_applications');
    }
};