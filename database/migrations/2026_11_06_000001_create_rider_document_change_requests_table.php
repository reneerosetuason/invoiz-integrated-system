<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rider document change requests.
 *
 * Riders cannot overwrite their approved documents directly. A replacement is
 * uploaded into this table (its own stored file) and only becomes the official
 * document when a Logistics Admin approves it. The official record itself stays
 * in `rider_application_documents`; this table keeps the review state, the
 * previous-file snapshot and the approval/rejection audit trail, so no parallel
 * document-management store is introduced.
 *
 * Additive only, guarded so re-running against the shared database is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rider_document_change_requests')) {
            return;
        }

        Schema::create('rider_document_change_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->foreignId('rider_application_id')->constrained('rider_applications')->cascadeOnDelete();

            $table->string('document_type', 50);

            // Proposed replacement file (private `rider-documents/...` path).
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);

            // Only driver's-license replacements use this today.
            $table->date('proposed_expiry')->nullable();
            $table->text('reason')->nullable();

            $table->string('status', 20)->default('pending');

            // Snapshot of the document that was official at approval time. The
            // file on disk is never deleted, so history stays viewable even
            // though the official row is updated in place.
            $table->foreignId('previous_document_id')->nullable()
                ->constrained('rider_application_documents')->nullOnDelete();
            $table->string('previous_stored_path')->nullable();
            $table->string('previous_original_filename')->nullable();
            $table->string('previous_mime_type', 100)->nullable();
            $table->unsignedBigInteger('previous_file_size')->nullable();
            $table->date('previous_expiry')->nullable();

            // Set once the request is approved: the official row that now
            // carries the replacement file.
            $table->foreignId('approved_document_id')->nullable()
                ->constrained('rider_application_documents')->nullOnDelete();

            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index(['rider_id', 'document_type', 'status'], 'rdcr_rider_type_status_idx');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_document_change_requests');
    }
};
