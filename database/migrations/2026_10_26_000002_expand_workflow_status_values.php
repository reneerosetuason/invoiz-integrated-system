<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'payments' => ['partially_refunded'],
            'deliveries' => ['lost', 'damaged', 'returned_to_seller'],
        ] as $tableName => $required) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            $column = collect(Schema::getColumns($tableName))->firstWhere('name', 'status');
            // VARCHAR already accepts these values; only restricted ENUMs need widening.
            if (! $column || $column['type_name'] !== 'enum') {
                continue;
            }
            preg_match_all("/'([^']+)'/", $column['type'], $matches);
            $existing = $matches[1];
            if (! array_diff($required, $existing)) {
                continue;
            }
            $values = array_values(array_unique(array_merge($existing, $required)));
            Schema::table($tableName, function (Blueprint $table) use ($column, $values): void {
                $status = $table->enum('status', $values)->nullable($column['nullable'])->default($column['default']);
                if ($column['collation']) {
                    $status->collation($column['collation']);
                }
                if ($column['comment']) {
                    $status->comment($column['comment']);
                }
                $status->change();
            });
        }
    }

    public function down(): void
    {
        // Keep valid workflow statuses and any records already using them.
    }
};
