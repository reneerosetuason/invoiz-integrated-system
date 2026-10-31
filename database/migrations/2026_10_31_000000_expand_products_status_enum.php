<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALLOWED = ['active', 'inactive', 'out_of_stock', 'draft', 'suspended'];

    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'status')) {
            throw new RuntimeException('Product status repair requires products.status.');
        }

        $column = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['products', 'status']
        );
        if (! $column) {
            throw new RuntimeException('Product status repair could not read products.status.');
        }

        $type = (string) $column->COLUMN_TYPE;
        if (stripos($type, 'enum(') !== 0) {
            throw new RuntimeException('Product status repair expects products.status to be an ENUM; found ' . $type . '.');
        }

        $existing = [];
        if (preg_match_all("/'([^']*)'/", $type, $matches)) {
            $existing = $matches[1];
        }

        $invalid = DB::table('products')
            ->whereNotNull('status')
            ->whereNotIn('status', self::ALLOWED)
            ->distinct()
            ->pluck('status')
            ->all();
        if ($invalid !== []) {
            throw new RuntimeException('Product status repair found unsupported existing values: ' . implode(', ', $invalid) . '.');
        }

        if (array_diff(self::ALLOWED, $existing) === []) {
            return;
        }

        $nullable = strtoupper((string) $column->IS_NULLABLE) === 'YES';
        $default = $column->COLUMN_DEFAULT === null
            ? ''
            : ' DEFAULT ' . DB::connection()->getPdo()->quote((string) $column->COLUMN_DEFAULT);

        $enum = "ENUM('active', 'inactive', 'out_of_stock', 'draft', 'suspended')";
        DB::statement(
            'ALTER TABLE `products` MODIFY `status` ' . $enum
            . ($nullable ? ' NULL' : ' NOT NULL') . $default
        );

        $after = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['products', 'status']
        );
        foreach (self::ALLOWED as $status) {
            if (! str_contains((string) ($after->COLUMN_TYPE ?? ''), "'" . $status . "'")) {
                throw new RuntimeException('Product status repair did not add the `' . $status . '` value.');
            }
        }
        if (strtoupper((string) $after->IS_NULLABLE) !== ($nullable ? 'YES' : 'NO')) {
            throw new RuntimeException('Product status repair changed nullability unexpectedly.');
        }
        if ((string) $after->COLUMN_DEFAULT !== (string) $column->COLUMN_DEFAULT) {
            throw new RuntimeException('Product status repair changed the default value unexpectedly.');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Product status widening is forward-only and cannot be rolled back automatically.');
    }
};
