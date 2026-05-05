<?php

declare(strict_types=1);

namespace Based\EloquentTypegen\Support\Scanners;

use Illuminate\Filesystem\Filesystem;

class MigrationScanner
{
    public function __construct(
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function scan(string $path): array
    {
        if (! $this->filesystem->isDirectory($path)) {
            return [];
        }

        $nullable = [];

        foreach ($this->filesystem->files($path) as $file) {
            $content = $this->filesystem->get($file->getPathname());
            $this->scanContent($content, $nullable);
        }

        return $nullable;
    }

    /**
     * @param array<string, array<string, bool>> $nullable
     */
    private function scanContent(string $content, array &$nullable): void
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $currentTable = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match("/Schema::create\(['\"]([^'\"]+)['\"]/", $line, $matches)) {
                $currentTable = $matches[1];
                $nullable[$currentTable] ??= [];
                continue;
            }

            if (preg_match("/Schema::table\(['\"]([^'\"]+)['\"]/", $line, $matches)) {
                $currentTable = $matches[1];
                $nullable[$currentTable] ??= [];
                continue;
            }

            if ($currentTable === null) {
                continue;
            }

            if (str_contains($line, '$table->nullableTimestamps()')) {
                $nullable[$currentTable]['created_at'] = true;
                $nullable[$currentTable]['updated_at'] = true;
            }

            if (str_contains($line, '$table->timestamps()->nullable()')) {
                $nullable[$currentTable]['created_at'] = true;
                $nullable[$currentTable]['updated_at'] = true;
            }

            if (str_contains($line, '$table->softDeletes()')) {
                $nullable[$currentTable]['deleted_at'] = true;
            }

            if (str_contains($line, '$table->rememberToken()')) {
                $nullable[$currentTable]['remember_token'] = true;
            }

            if (! str_contains($line, '$table->')) {
                continue;
            }

            if (! str_contains($line, 'nullable()')) {
                continue;
            }

            if (preg_match('/\\$table->\\w+\\([\'\"]([^\'\"]+)[\'\"]/', $line, $matches)) {
                $column = $matches[1];
                $nullable[$currentTable][$column] = true;
            }
        }
    }
}
