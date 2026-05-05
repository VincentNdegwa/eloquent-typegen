<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Scanners;

use Illuminate\Filesystem\Filesystem;

class MigrationScanner
{
    /**
     * Maps Laravel Blueprint method names → TypeScript-friendly type strings.
     * These are used when a model field has no cast — we fall back to what
     * the migration column type tells us.
     *
     * @var array<string, string>
     */
    private const COLUMN_TYPE_MAP = [
        // integers
        'id' => 'number',
        'tinyInteger' => 'number',
        'smallInteger' => 'number',
        'mediumInteger' => 'number',
        'integer' => 'number',
        'bigInteger' => 'number',
        'unsignedTinyInteger' => 'number',
        'unsignedSmallInteger' => 'number',
        'unsignedMediumInteger' => 'number',
        'unsignedInteger' => 'number',
        'unsignedBigInteger' => 'number',
        'increments' => 'number',
        'tinyIncrements' => 'number',
        'smallIncrements' => 'number',
        'mediumIncrements' => 'number',
        'bigIncrements' => 'number',
        'foreignId' => 'number',
        'foreignUuid' => 'string',
        'foreignUlid' => 'string',
        // floats / decimals
        'float' => 'number',
        'double' => 'number',
        'decimal' => 'number',
        'unsignedFloat' => 'number',
        'unsignedDouble' => 'number',
        'unsignedDecimal' => 'number',
        // booleans
        'boolean' => 'boolean',
        // strings
        'char' => 'string',
        'string' => 'string',
        'tinyText' => 'string',
        'text' => 'string',
        'mediumText' => 'string',
        'longText' => 'string',
        'uuid' => 'string',
        'ulid' => 'string',
        'ipAddress' => 'string',
        'macAddress' => 'string',
        'enum' => 'string',
        'set' => 'string',
        // dates
        'date' => 'date',
        'dateTime' => 'date',
        'dateTimeTz' => 'date',
        'time' => 'string',
        'timeTz' => 'string',
        'timestamp' => 'date',
        'timestampTz' => 'date',
        'year' => 'number',
        // json
        'json' => 'json',
        'jsonb' => 'json',
        // binary
        'binary' => 'string',
        'geometry' => 'string',
    ];

    public function __construct(
        private readonly Filesystem $filesystem = new Filesystem,
    ) {}

    /**
     * Scan all migration files in the given directory (recursively).
     *
     * Returns two maps keyed by table name:
     *   - nullable: [column => true]
     *   - columnTypes: [column => 'number'|'string'|'boolean'|'date'|'json']
     *
     * @return array{
     *     nullable: array<string, array<string, bool>>,
     *     columnTypes: array<string, array<string, string>>
     * }
     */
    public function scan(string $path): array
    {
        if (! $this->filesystem->isDirectory($path)) {
            return ['nullable' => [], 'columnTypes' => []];
        }

        $nullable = [];
        $columnTypes = [];

        // allFiles() is recursive — catches migrations in subdirectories
        foreach ($this->filesystem->allFiles($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = $this->filesystem->get($file->getPathname());
            $this->scanContent($content, $nullable, $columnTypes);
        }

        return ['nullable' => $nullable, 'columnTypes' => $columnTypes];
    }

    /**
     * @param  array<string, array<string, bool>>  $nullable
     * @param  array<string, array<string, string>>  $columnTypes
     */
    private function scanContent(string $content, array &$nullable, array &$columnTypes): void
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $currentTable = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // ── Table context detection ────────────────────────────────────
            if (preg_match("/Schema::(?:create|table)\s*\(\s*['\"]([^'\"]+)['\"]/", $line, $m)) {
                $currentTable = $m[1];
                $nullable[$currentTable] ??= [];
                $columnTypes[$currentTable] ??= [];

                continue;
            }

            if ($currentTable === null || ! str_contains($line, '$table->')) {
                continue;
            }

            // ── Macro helpers ──────────────────────────────────────────────
            if (str_contains($line, '$table->id(')) {
                $columnTypes[$currentTable]['id'] = 'number';
                $nullable[$currentTable]['id'] = false;

                continue;
            }

            if (str_contains($line, '$table->timestamps()') || str_contains($line, '$table->nullableTimestamps()')) {
                $isNullable = str_contains($line, 'nullable');
                $columnTypes[$currentTable]['created_at'] = 'date';
                $columnTypes[$currentTable]['updated_at'] = 'date';
                $nullable[$currentTable]['created_at'] = $isNullable;
                $nullable[$currentTable]['updated_at'] = $isNullable;

                continue;
            }

            if (str_contains($line, '$table->softDeletes()')) {
                $columnTypes[$currentTable]['deleted_at'] = 'date';
                $nullable[$currentTable]['deleted_at'] = true;

                continue;
            }

            if (str_contains($line, '$table->rememberToken()')) {
                $columnTypes[$currentTable]['remember_token'] = 'string';
                $nullable[$currentTable]['remember_token'] = true;

                continue;
            }

            // ── Regular column definitions ─────────────────────────────────
            // Matches: $table->unsignedBigInteger('column_name')
            //          $table->string('column_name', 100)
            if (! preg_match('/\$table->(\w+)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $line, $m)) {
                continue;
            }

            $method = $m[1];
            $column = $m[2];

            // Skip schema helpers that aren't column definitions
            if (in_array($method, ['dropColumn', 'dropForeign', 'dropIndex', 'dropPrimary',
                'dropUnique', 'renameColumn', 'foreign', 'index', 'unique', 'primary'], true)) {
                continue;
            }

            // Record the TypeScript-friendly type if we know this method
            if (isset(self::COLUMN_TYPE_MAP[$method])) {
                $columnTypes[$currentTable][$column] = self::COLUMN_TYPE_MAP[$method];
            }

            // Record nullability
            $nullable[$currentTable][$column] = str_contains($line, '->nullable()');
        }
    }
}
