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
        Schema::create('rider_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_application_id')->constrained('rider_applications')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();
        });

        // Migrate legacy JSON documents (license_document / registration_document)
        // into the new table so previously uploaded files remain accessible.
        $legacyTypes = [
            'license_document' => 'drivers_license',
            'registration_document' => 'vehicle_registration',
        ];

        \App\Models\RiderApplication::whereNotNull('documents')->chunkById(100, function ($applications) use ($legacyTypes) {
            foreach ($applications as $application) {
                foreach (($application->documents ?? []) as $key => $path) {
                    $type = $legacyTypes[$key] ?? $key;
                    $absolute = public_path($path);

                    \App\Models\RiderApplicationDocument::firstOrCreate([
                        'rider_application_id' => $application->id,
                        'document_type' => $type,
                        'stored_path' => $path,
                    ], [
                        'original_filename' => basename($path),
                        'mime_type' => file_exists($absolute)
                            ? (mime_content_type($absolute) ?: 'application/octet-stream')
                            : 'application/octet-stream',
                        'file_size' => file_exists($absolute) ? filesize($absolute) : 0,
                    ]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rider_application_documents');
    }
};
