<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Resolvers;

use VincentNdegwa\EloquentTypegen\Support\Scanners\MigrationScanner;

class NullabilityResolver
{
    /** @var array<string, array<string, bool>> */
    private array $nullableByTable = [];

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
        $this->nullableByTable = $this->scanner->scan($path);
    }

    public function isNullable(string $table, string $column): bool
    {
        if ($column === 'deleted_at' || $column === 'remember_token') {
            return true;
        }

        return $this->nullableByTable[$table][$column] ?? false;
    }
}
