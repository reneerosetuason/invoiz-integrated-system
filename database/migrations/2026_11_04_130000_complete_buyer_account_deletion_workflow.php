<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_deletion_requests', function (Blueprint $table): void {
            foreach (['hold_reason', 'previous_status', 'previous_account_status'] as $column) {
                if (! Schema::hasColumn('account_deletion_requests', $column)) {
                    $table->text($column)->nullable();
                }
            }
            foreach (['reminder_sent_at', 'restored_at'] as $column) {
                if (! Schema::hasColumn('account_deletion_requests', $column)) {
                    $table->timestamp($column)->nullable();
                }
            }
            if (! Schema::hasColumn('account_deletion_requests', 'restored_by')) {
                $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
        foreach ([
            'deletion_user_status_idx' => ['user_id', 'status'],
            'deletion_due_status_idx' => ['status', 'scheduled_archive_at'],
        ] as $name => $columns) {
            if (! collect(Schema::getIndexes('account_deletion_requests'))->contains('name', $name)) {
                Schema::table('account_deletion_requests', fn (Blueprint $table) => $table->index($columns, $name));
            }
        }
        if (! Schema::hasColumn('users', 'archived_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('archived_at')->nullable()->index());
        }
        DB::table('account_deletion_requests')->where('status', 'blocked')->update(['status' => 'on_hold']);
        DB::table('account_deletion_requests')->where('status', 'archived')->update(['status' => 'completed']);
    }

    public function down(): void
    {
        throw new RuntimeException('Account archival and restoration history must be retained.');
    }
};
