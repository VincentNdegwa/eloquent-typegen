<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Scanners;

use Illuminate\Filesystem\Filesystem;

class MigrationScanner
{
    /**
     * Maps Laravel Blueprint method names → TypeScript-friendly type strings.
     *
     * @var array<string, string>
     */
    private array $columnTypeMap;

    public function __construct(
        private readonly Filesystem $filesystem = new Filesystem,
    ) {
        $this->columnTypeMap = config('typegen.migration_type_map', [
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
        ]);
    }

    /**
     * Scan all migration files in the given directory (recursively).
     *
     * Returns maps keyed by table name:
     *   - nullable: [column => true]
     *   - columnTypes: [column => 'number'|'string'|'boolean'|'date'|'json']
     *   - constraints: [column => ['unsigned' => bool, 'min' => int|null, 'max' => int|null]]
     *
     * @return array{
     *     nullable: array<string, array<string, bool>>,
     *     columnTypes: array<string, array<string, string>>,
     *     constraints: array<string, array<string, array<string, bool|int|null>>>
     * }
     */
    public function scan(string $path): array
    {
        if (! $this->filesystem->isDirectory($path)) {
            return ['nullable' => [], 'columnTypes' => [], 'constraints' => []];
        }

        $nullable = [];
        $columnTypes = [];
        $constraints = [];

        foreach ($this->filesystem->allFiles($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = $this->filesystem->get($file->getPathname());
            $this->scanContent($content, $nullable, $columnTypes, $constraints);
        }

        return ['nullable' => $nullable, 'columnTypes' => $columnTypes, 'constraints' => $constraints];
    }

    /**
     * @param  array<string, array<string, bool>>  $nullable
     * @param  array<string, array<string, string>>  $columnTypes
     * @param  array<string, array<string, array<string, bool|int|null>>>  $constraints
     */
    private function scanContent(string $content, array &$nullable, array &$columnTypes, array &$constraints): void
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $currentTable = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match("/Schema::(?:create|table)\s*\(\s*['\"]([^'\"]+)['\"]/", $line, $m)) {
                $currentTable = $m[1];
                $nullable[$currentTable] ??= [];
                $columnTypes[$currentTable] ??= [];
                $constraints[$currentTable] ??= [];

                continue;
            }

            if ($currentTable === null || ! str_contains($line, '$table->')) {
                continue;
            }

            if (str_contains($line, '$table->id(')) {
                $columnTypes[$currentTable]['id'] = 'number';
                $nullable[$currentTable]['id'] = false;
                $constraints[$currentTable]['id'] = ['unsigned' => true, 'min' => null, 'max' => null];

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

            if (! preg_match('/\$table->(\w+)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $line, $m)) {
                continue;
            }

            $method = $m[1];
            $column = $m[2];

            if (in_array($method, ['dropColumn', 'dropForeign', 'dropIndex', 'dropPrimary',
                'dropUnique', 'renameColumn', 'foreign', 'index', 'unique', 'primary'], true)) {
                continue;
            }

            if (isset($this->columnTypeMap[$method])) {
                $columnTypes[$currentTable][$column] = $this->columnTypeMap[$method];
            }

            $nullable[$currentTable][$column] = str_contains($line, '->nullable()');

            $constraint = [
                'unsigned' => str_contains($method, 'unsigned') || str_contains($line, '->unsigned()'),
                'min' => null,
                'max' => null,
            ];

            if (preg_match('/->length\s*\(\s*(\d+)\s*\)/', $line, $lengthMatch)) {
                $constraint['max'] = (int) $lengthMatch[1];
            } elseif (preg_match('/->max\s*\(\s*(\d+)\s*\)/', $line, $maxMatch)) {
                $constraint['max'] = (int) $maxMatch[1];
            }

            if (preg_match('/->min\s*\(\s*(\d+)\s*\)/', $line, $minMatch)) {
                $constraint['min'] = (int) $minMatch[1];
            }

            $constraints[$currentTable][$column] = $constraint;
        }
    }
}
