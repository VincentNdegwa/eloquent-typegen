<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use VincentNdegwa\EloquentTypegen\Support\Generators\FormRequestGenerator;
use VincentNdegwa\EloquentTypegen\Support\Generators\ResourceGenerator;
use VincentNdegwa\EloquentTypegen\Support\Generators\TypeScriptGenerator;
use VincentNdegwa\EloquentTypegen\Support\Generators\ZodGenerator;
use VincentNdegwa\EloquentTypegen\Support\Scanners\FormRequestScanner;
use VincentNdegwa\EloquentTypegen\Support\Scanners\ModelScanner;
use VincentNdegwa\EloquentTypegen\Support\Scanners\ResourceScanner;

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

        // Warn if output path conflicts with Laravel's default types folder
        if (str_ends_with($outputPath, '/types') || str_ends_with($outputPath, '\\types') || $outputPath === 'types') {
            $this->warn('Output path is set to Laravel\'s default "types" folder.');
            $this->warn('This may conflict with Laravel\'s auto-generated types.');
            $this->warn('Consider using "eloquent-types" or a custom path instead.');
            if (! $this->confirm('Continue anyway?', false)) {
                return self::FAILURE;
            }
        }

        $includeRelations = ! $this->option('no-relations')
            && (bool) config('typegen.include_relationships');

        $files = [];

        // Generate model types
        if (! empty($models)) {
            // Models go to models/ subdirectory
            $modelOutputPath = $outputPath.'/models';
            $generator = new TypeScriptGenerator($modelOutputPath, $includeRelations);
            $files = array_merge($files, $generator->generate($models));

            // Generate Zod schemas if enabled
            if ($this->option('zod') || config('typegen.generate_zod', false)) {
                $zodOutputPath = config('typegen.zod_output_path') ?? $modelOutputPath;
                $generateZodIndex = config('typegen.generate_zod_index', true);
                $zodGenerator = new ZodGenerator($zodOutputPath, $includeRelations, $generateZodIndex);
                $files = array_merge($files, $zodGenerator->generate($models));
            }
        }

        // Generate resource types if enabled
        if ($this->option('resources') || config('typegen.generate_resources', false)) {
            $resourceScanner = new ResourceScanner;
            $resourceScanner->withModels($models);
            $resources = $resourceScanner->scan();

            if (! empty($resources)) {
                // Build model map for resource extension (interfaceName => ModelMetadata)
                $modelMap = [];
                foreach ($models as $model) {
                    $modelMap[$model->interfaceName] = $model;
                }

                // Resources go to resources/ subdirectory at root level (not under models/)
                $resourceOutputPath = config('typegen.output_path', 'resources/js/types').'/resources';
                $resourceGenerator = new ResourceGenerator($resourceOutputPath, $modelMap);
                $files = array_merge($files, $resourceGenerator->generate($resources));
            }
        }

        // Generate form request types if enabled
        if ($this->option('requests') || config('typegen.generate_requests', false)) {
            $formRequestScanner = new FormRequestScanner;
            $requests = $formRequestScanner->scan();

            if (! empty($requests)) {
                // Requests go to requests/ subdirectory at root level (not under models/)
                $requestOutputPath = config('typegen.output_path', 'resources/js/types').'/requests';
                $formRequestGenerator = new FormRequestGenerator($requestOutputPath);
                $files = array_merge($files, $formRequestGenerator->generate($requests));
            }
        }

        if (empty($files)) {
            $this->warn('No types were generated.');

            return self::SUCCESS;
        }

        // Generate a single root index.ts that re-exports everything
        $rootIndexPath = config('typegen.root_index_path', 'types.ts');
        if ($rootIndexPath !== null) {
            $files[$outputPath.'/'.$rootIndexPath] = $this->renderRootIndex($outputPath);
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

    /**
     * Generate a root index.ts that re-exports all types from subdirectories.
     */
    protected function renderRootIndex(string $outputPath): string
    {
        $lines = [
            '// This file is auto-generated by eloquent-typegen. Do not edit manually.',
            '',
            '// Models',
            "export * from './models';",
            '',
            '// Resources',
            "export * from './resources';",
            '',
            '// Requests',
            "export * from './requests';",
            '',
        ];

        return implode("\n", $lines);
    }
}
