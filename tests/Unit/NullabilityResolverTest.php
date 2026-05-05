<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use VincentNdegwa\EloquentTypegen\Support\Resolvers\NullabilityResolver;

it('combines migration data with special columns', function () {
    $resolver = new NullabilityResolver(false);

    // Test special columns that are always nullable
    expect($resolver->isNullable('users', 'deleted_at'))->toBeTrue()
        ->and($resolver->isNullable('users', 'remember_token'))->toBeTrue();
});

it('returns false for regular columns when migrations disabled', function () {
    $resolver = new NullabilityResolver(false);

    expect($resolver->isNullable('users', 'name'))->toBeFalse()
        ->and($resolver->isNullable('users', 'email'))->toBeFalse();
});

it('returns column type from migrations', function () {
    $filesystem = new Filesystem;

    // Create migration directory in the expected location
    $migrationDir = base_path('database/migrations');
    $filesystem->ensureDirectoryExists($migrationDir);

    $migration = <<<'PHP'
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->unsignedBigInteger('user_id')->nullable();
});
PHP;

    $filesystem->put($migrationDir.'/2026_05_05_000000_create_posts_table.php', $migration);

    config(['typegen.migration_type_map' => [
        'id' => 'number',
        'string' => 'string',
        'unsignedBigInteger' => 'number',
    ]]);

    $resolver = new NullabilityResolver(true);
    $resolver->bootstrap();

    expect($resolver->columnType('posts', 'id'))->toBe('number')
        ->and($resolver->columnType('posts', 'title'))->toBe('string')
        ->and($resolver->columnType('posts', 'user_id'))->toBe('number')
        ->and($resolver->columnType('posts', 'nonexistent'))->toBeNull();

    $filesystem->delete($migrationDir.'/2026_05_05_000000_create_posts_table.php');
});

it('returns null for column type when migrations disabled', function () {
    $resolver = new NullabilityResolver(false);
    $resolver->bootstrap();

    expect($resolver->columnType('posts', 'title'))->toBeNull();
});

it('tableKnown returns true when migration exists', function () {
    $filesystem = new Filesystem;

    // Create migration directory in the expected location
    $migrationDir = base_path('database/migrations');
    $filesystem->ensureDirectoryExists($migrationDir);

    $migration = <<<'PHP'
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('posts', function (Blueprint $table) {
    $table->id();
});
PHP;

    $filesystem->put($migrationDir.'/2026_05_05_000000_create_posts_table.php', $migration);

    config(['typegen.migration_type_map' => ['id' => 'number']]);

    $resolver = new NullabilityResolver(true);
    $resolver->bootstrap();

    expect($resolver->tableKnown('posts'))->toBeTrue()
        ->and($resolver->tableKnown('nonexistent'))->toBeFalse();

    $filesystem->delete($migrationDir.'/2026_05_05_000000_create_posts_table.php');
});

it('tableKnown returns false when migrations disabled', function () {
    $resolver = new NullabilityResolver(false);
    $resolver->bootstrap();

    expect($resolver->tableKnown('posts'))->toBeFalse();
});
