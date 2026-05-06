<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Tests\Unit;

use ReflectionClass;
use VincentNdegwa\EloquentTypegen\Console\GenerateTypesCommand;

it('generates root index barrel with correct structure', function () {
    $command = new GenerateTypesCommand;
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('renderRootIndex');
    $method->setAccessible(true);

    $content = $method->invoke($command, '/test/path');

    expect($content)->toContain('// Models');
    expect($content)->toContain("export * from './models';");
    expect($content)->toContain('// Resources');
    expect($content)->toContain("export * from './resources';");
    expect($content)->toContain('// Requests');
    expect($content)->toContain("export * from './requests';");
});

it('respects root_index_path config when set', function () {
    config(['typegen.root_index_path' => 'types.ts']);
    expect(config('typegen.root_index_path'))->toBe('types.ts');
});

it('respects root_index_path config when null', function () {
    config(['typegen.root_index_path' => null]);
    expect(config('typegen.root_index_path'))->toBeNull();
});
