<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use VincentNdegwa\EloquentTypegen\Support\Generators\TypeScriptGenerator;
use VincentNdegwa\EloquentTypegen\Support\Generators\ZodGenerator;
use VincentNdegwa\EloquentTypegen\Support\Generators\ResourceGenerator;
use VincentNdegwa\EloquentTypegen\Support\Generators\FormRequestGenerator;
use VincentNdegwa\EloquentTypegen\Support\Scanners\ModelScanner;
use VincentNdegwa\EloquentTypegen\Support\Scanners\ResourceScanner;
use VincentNdegwa\EloquentTypegen\Support\Scanners\FormRequestScanner;

class GenerateTypesCommand extends Command
{
    protected $signature = 'typegen:generate
        {--model=* : Only generate for named model(s)}
        {--path= : Override output directory}
        {--dry-run : Print to console, write nothing}
        {--no-relations : Skip relationship fields}
        {--zod : Generate Zod schemas}
        {--resources : Generate API Resource types}
        {--requests : Generate Form Request types}';

    protected $description = 'Generate TypeScript types from Eloquent models';

    public function handle(): int
    {
        $scanner = new ModelScanner;
        /** @var array<string> */
        $modelOption = $this->option('model');
        $models = $scanner->scan($modelOption);

        if (empty($models) && ! $this->option('resources') && ! $this->option('requests')) {
            $this->warn('No models matched the given criteria.');

            return self::SUCCESS;
        }

        $outputPathOption = $this->option('path');
        $outputPath = (is_string($outputPathOption) && $outputPathOption !== '')
            ? $outputPathOption
            : (string) config('typegen.output_path', '');

        $includeRelations = ! $this->option('no-relations')
            && (bool) config('typegen.include_relationships');

        $files = [];

        // Generate model types
        if (! empty($models)) {
            $generator = new TypeScriptGenerator($outputPath, $includeRelations);
            $files = array_merge($files, $generator->generate($models));

            // Generate Zod schemas if enabled
            if ($this->option('zod') || config('typegen.generate_zod', false)) {
                $zodOutputPath = config('typegen.zod_output_path') ?? $outputPath;
                $zodGenerator = new ZodGenerator($zodOutputPath, $includeRelations);
                $files = array_merge($files, $zodGenerator->generate($models));
            }
        }

        // Generate resource types if enabled
        if ($this->option('resources') || config('typegen.generate_resources', false)) {
            $resourceScanner = new ResourceScanner;
            $resources = $resourceScanner->scan();

            if (! empty($resources)) {
                $resourceOutputPath = config('typegen.output_path', 'resources/js/types').'/resources';
                $resourceGenerator = new ResourceGenerator($resourceOutputPath);
                $files = array_merge($files, $resourceGenerator->generate($resources));
            }
        }

        // Generate form request types if enabled
        if ($this->option('requests') || config('typegen.generate_requests', false)) {
            $formRequestScanner = new FormRequestScanner;
            $requests = $formRequestScanner->scan();

            if (! empty($requests)) {
                $requestOutputPath = config('typegen.output_path', 'resources/js/types').'/requests';
                $formRequestGenerator = new FormRequestGenerator($requestOutputPath);
                $files = array_merge($files, $formRequestGenerator->generate($requests));
            }
        }

        if (empty($files)) {
            $this->warn('No types were generated.');

            return self::SUCCESS;
        }

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
