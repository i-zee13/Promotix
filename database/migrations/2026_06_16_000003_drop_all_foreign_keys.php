<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops every foreign key in the current database so phpMyAdmin import/drop/truncate
 * does not fail on constraint errors. Column values are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->informationSchemaAvailable()) {
            return;
        }

        foreach ($this->foreignKeys() as $foreignKey) {
            $table = $foreignKey['table'];
            $constraint = $foreignKey['constraint'];

            if (! Schema::hasTable($table)) {
                continue;
            }

            try {
                DB::statement(sprintf(
                    'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                    str_replace('`', '``', $table),
                    str_replace('`', '``', $constraint),
                ));
            } catch (\Throwable) {
                // Already removed or renamed — continue.
            }
        }
    }

    public function down(): void
    {
        // Foreign keys are not recreated automatically. Re-run fresh migrations on a new DB
        // or restore from a schema dump that includes constraints.
    }

    /**
     * @return list<array{table: string, constraint: string}>
     */
    private function foreignKeys(): array
    {
        $database = DB::getDatabaseName();
        $rows = DB::select(
            'SELECT TABLE_NAME AS table_name, CONSTRAINT_NAME AS constraint_name
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ?
               AND CONSTRAINT_TYPE = ?
             ORDER BY TABLE_NAME, CONSTRAINT_NAME',
            [$database, 'FOREIGN KEY']
        );

        return array_map(static fn ($row) => [
            'table' => (string) $row->table_name,
            'constraint' => (string) $row->constraint_name,
        ], $rows);
    }

    private function informationSchemaAvailable(): bool
    {
        try {
            DB::select('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
};
