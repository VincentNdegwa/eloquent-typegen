<?php

declare(strict_types=1);

namespace Based\EloquentTypegen\Console;

use Based\EloquentTypegen\Support\Generators\TypeScriptGenerator;
use Based\EloquentTypegen\Support\Scanners\ModelScanner;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class GenerateTypesCommand extends Command
{
    protected $signature = 'typegen:generate
        {--model=* : Only generate for named model(s)}
        {--path= : Override output directory}
        {--dry-run : Print to console, write nothing}
        {--no-relations : Skip relationship fields}';

    protected $description = 'Generate TypeScript types from Eloquent models';

    public function handle(): int
    {
        $scanner = new ModelScanner;
        $models = $scanner->scan($this->option('model'));

        if (empty($models)) {
            $this->warn('No models matched the given criteria.');

            return self::SUCCESS;
        }

        $outputPath = $this->option('path')
            ? (string) $this->option('path')
            : (string) config('typegen.output_path');

        $includeRelations = ! $this->option('no-relations')
            && (bool) config('typegen.include_relationships');

        $generator = new TypeScriptGenerator($outputPath, $includeRelations);
        $files = $generator->generate($models);

        if ($this->option('dry-run')) {
            foreach ($files as $path => $content) {
                $this->line('');
                $this->info($path);
                $this->line($content);
            }

            return self::SUCCESS;
        }

        $filesystem = new Filesystem;

        foreach ($files as $path => $content) {
            $directory = Str::beforeLast($path, '/');
            if (! $filesystem->isDirectory($directory)) {
                $filesystem->makeDirectory($directory, 0755, true);
            }
            $filesystem->put($path, $content);
        }

        $this->info('TypeScript types generated.');

        return self::SUCCESS;
    }
}
