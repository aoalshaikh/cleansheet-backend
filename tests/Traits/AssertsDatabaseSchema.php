<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait AssertsDatabaseSchema
{
    /**
     * Assert that a table exists.
     */
    protected function assertTableExists(string $table): void
    {
        $this->assertTrue(
            Schema::hasTable($table),
            "Failed asserting that table '{$table}' exists."
        );
    }

    /**
     * Assert that a table does not exist.
     */
    protected function assertTableDoesNotExist(string $table): void
    {
        $this->assertFalse(
            Schema::hasTable($table),
            "Failed asserting that table '{$table}' does not exist."
        );
    }

    /**
     * Assert that a table has specific columns.
     *
     * @param array<string> $columns
     */
    protected function assertTableHasColumns(string $table, array $columns): void
    {
        $this->assertTrue(
            Schema::hasColumns($table, $columns),
            "Failed asserting that table '{$table}' has columns: " . implode(', ', $columns)
        );
    }

    /**
     * Assert that a column exists.
     */
    protected function assertColumnExists(string $table, string $column): void
    {
        $this->assertTrue(
            Schema::hasColumn($table, $column),
            "Failed asserting that column '{$column}' exists in table '{$table}'."
        );
    }

    /**
     * Assert that a column does not exist.
     */
    protected function assertColumnDoesNotExist(string $table, string $column): void
    {
        $this->assertFalse(
            Schema::hasColumn($table, $column),
            "Failed asserting that column '{$column}' does not exist in table '{$table}'."
        );
    }

    /**
     * Assert that a column is nullable.
     */
    protected function assertColumnNullable(string $table, string $column): void
    {
        $columnInfo = $this->getColumnInfo($table, $column);
        $this->assertTrue(
            $columnInfo->nullable,
            "Failed asserting that column '{$column}' in table '{$table}' is nullable."
        );
    }

    /**
     * Assert that a column is not nullable.
     */
    protected function assertColumnNotNullable(string $table, string $column): void
    {
        $columnInfo = $this->getColumnInfo($table, $column);
        $this->assertFalse(
            $columnInfo->nullable,
            "Failed asserting that column '{$column}' in table '{$table}' is not nullable."
        );
    }

    /**
     * Assert that a column has a specific type.
     */
    protected function assertColumnType(string $table, string $column, string $type): void
    {
        $columnInfo = $this->getColumnInfo($table, $column);
        $this->assertEquals(
            strtolower($type),
            strtolower($columnInfo->type),
            "Failed asserting that column '{$column}' in table '{$table}' is of type '{$type}'."
        );
    }

    /**
     * Assert that a column has a default value.
     */
    protected function assertColumnHasDefault(string $table, string $column, mixed $default): void
    {
        $columnInfo = $this->getColumnInfo($table, $column);
        $this->assertEquals(
            $default,
            $columnInfo->default,
            "Failed asserting that column '{$column}' in table '{$table}' has default value '{$default}'."
        );
    }

    /**
     * Assert that a column has no default value.
     */
    protected function assertColumnHasNoDefault(string $table, string $column): void
    {
        $columnInfo = $this->getColumnInfo($table, $column);
        $this->assertNull(
            $columnInfo->default,
            "Failed asserting that column '{$column}' in table '{$table}' has no default value."
        );
    }

    /**
     * Assert that a table has an index.
     *
     * @param array<string>|string $columns
     */
    protected function assertTableHasIndex(string $table, array|string $columns): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $this->assertTrue(
            $this->hasIndex($table, $columns),
            "Failed asserting that table '{$table}' has index on columns: " . implode(', ', $columns)
        );
    }

    /**
     * Assert that a table does not have an index.
     *
     * @param array<string>|string $columns
     */
    protected function assertTableDoesNotHaveIndex(string $table, array|string $columns): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $this->assertFalse(
            $this->hasIndex($table, $columns),
            "Failed asserting that table '{$table}' does not have index on columns: " . implode(', ', $columns)
        );
    }

    /**
     * Assert that a table has a foreign key.
     */
    protected function assertTableHasForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn = 'id'
    ): void {
        $this->assertTrue(
            $this->hasForeignKey($table, $column, $referencedTable, $referencedColumn),
            "Failed asserting that table '{$table}' has foreign key '{$column}' referencing '{$referencedTable}.{$referencedColumn}'."
        );
    }

    /**
     * Assert that a table does not have a foreign key.
     */
    protected function assertTableDoesNotHaveForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn = 'id'
    ): void {
        $this->assertFalse(
            $this->hasForeignKey($table, $column, $referencedTable, $referencedColumn),
            "Failed asserting that table '{$table}' does not have foreign key '{$column}' referencing '{$referencedTable}.{$referencedColumn}'."
        );
    }

    /**
     * Get column information.
     */
    protected function getColumnInfo(string $table, string $column): object
    {
        return DB::connection()->getDoctrineColumn($table, $column);
    }

    /**
     * Check if a table has an index on specific columns.
     *
     * @param array<string> $columns
     */
    protected function hasIndex(string $table, array $columns): bool
    {
        $indexes = DB::connection()->getDoctrineSchemaManager()->listTableIndexes($table);

        foreach ($indexes as $index) {
            if ($index->getColumns() === $columns) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a foreign key exists.
     */
    protected function hasForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn = 'id'
    ): bool {
        $foreignKeys = DB::connection()->getDoctrineSchemaManager()->listTableForeignKeys($table);

        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey->getLocalColumns()[0] === $column &&
                $foreignKey->getForeignTableName() === $referencedTable &&
                $foreignKey->getForeignColumns()[0] === $referencedColumn) {
                return true;
            }
        }

        return false;
    }
}
