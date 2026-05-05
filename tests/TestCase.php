<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Tests;

use VincentNdegwa\EloquentTypegen\EloquentTypegenServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [EloquentTypegenServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('typegen.output_path', 'tests-output');
        $app['config']->set('typegen.model_paths', ['Models']);
        $app['config']->set('typegen.include_relationships', true);
        $app['config']->set('typegen.read_migrations', true);
        $app['config']->set('typegen.generate_helpers', true);
        $app['config']->set('typegen.generate_index', true);
    }
}
