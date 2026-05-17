<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Resolvers;

use VincentNdegwa\EloquentTypegen\Support\Scanners\MigrationScanner;

class NullabilityResolver
{
    /** @var array<string, array<string, bool>> */
    private array $nullableByTable = [];

    /** @var array<string, array<string, string>> */
    private array $columnTypesByTable = [];

    /** @var array<string, array<string, array<string, bool|int|null>>> */
    private array $constraintsByTable = [];

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
        $this->constraintsByTable = $result['constraints'];
    }

    public function isNullable(string $table, string $column): bool
    {
        if ($column === 'deleted_at' || $column === 'remember_token') {
            return true;
        }

        return $this->nullableByTable[$table][$column] ?? false;
    }

    public function columnType(string $table, string $column): ?string
    {
        return $this->columnTypesByTable[$table][$column] ?? null;
    }

    public function tableKnown(string $table): bool
    {
        return isset($this->nullableByTable[$table]) || isset($this->columnTypesByTable[$table]);
    }

    /**
     * @return array{unsigned: bool, min: int|null, max: int|null}
     */
    public function getConstraints(string $table, string $column): array
    {
        $constraints = $this->constraintsByTable[$table][$column] ?? ['unsigned' => false, 'min' => null, 'max' => null];

        return [
            'unsigned' => (bool) ($constraints['unsigned'] ?? false),
            'min' => $constraints['min'] !== null ? (int) $constraints['min'] : null,
            'max' => $constraints['max'] !== null ? (int) $constraints['max'] : null,
        ];
    }
}
