<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen;

use Illuminate\Support\ServiceProvider;
use VincentNdegwa\EloquentTypegen\Console\GenerateTypesCommand;

class EloquentTypegenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/typegen.php', 'typegen');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/typegen.php' => config_path('typegen.php'),
        ], 'typegen-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTypesCommand::class,
            ]);
        }
    }
}
