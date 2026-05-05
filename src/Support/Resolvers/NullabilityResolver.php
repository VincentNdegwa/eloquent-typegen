<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Resolvers;

use VincentNdegwa\EloquentTypegen\Support\Scanners\MigrationScanner;

class NullabilityResolver
{
    /** @var array<string, array<string, bool>> */
    private array $nullableByTable = [];

    /**
     * Column types inferred from migration method names.
     * Used as a fallback when a model field has no explicit cast.
     * Values are internal type tokens: 'number'|'string'|'boolean'|'date'|'json'
     *
     * @var array<string, array<string, string>>
     */
    private array $columnTypesByTable = [];

    public function __construct(
        private readonly bool $readMigrations,
        private readonly MigrationScanner $scanner = new MigrationScanner,
    ) {}

    public function bootstrap(): void
    {
        if (! $this->readMigrations) {
            return;
        }

        $path = base_path('database/migrations');
        $result = $this->scanner->scan($path);

        $this->nullableByTable = $result['nullable'];
        $this->columnTypesByTable = $result['columnTypes'];
    }

    public function isNullable(string $table, string $column): bool
    {
        if ($column === 'deleted_at' || $column === 'remember_token') {
            return true;
        }

        return $this->nullableByTable[$table][$column] ?? false;
    }

    /**
     * Return the migration-inferred type token for a column, or null if unknown.
     * Token values: 'number' | 'string' | 'boolean' | 'date' | 'json'
     */
    public function columnType(string $table, string $column): ?string
    {
        return $this->columnTypesByTable[$table][$column] ?? null;
    }

    /**
     * Returns true if the migration scanner has any information at all for this table.
     * Useful to distinguish "no migration found" from "migration found, field not in it".
     */
    public function tableKnown(string $table): bool
    {
        return isset($this->nullableByTable[$table]) || isset($this->columnTypesByTable[$table]);
    }
}
