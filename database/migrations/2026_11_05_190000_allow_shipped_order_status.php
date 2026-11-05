<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $column = collect(Schema::getColumns('orders'))->firstWhere('name', 'status');
        if (! $column || $column['type_name'] !== 'enum') { return; }
        preg_match_all("/'([^']+)'/", $column['type'], $matches);
        if (in_array('shipped', $matches[1], true)) { return; }
        Schema::table('orders', function (Blueprint $table) use ($column, $matches) {
            $field = $table->enum('status', [...$matches[1], 'shipped'])->nullable($column['nullable'])->default($column['default']);
            if ($column['collation']) { $field->collation($column['collation']); }
            $field->change();
        });
    }
    public function down(): void { /* Preserve valid shipped orders. */ }
};
