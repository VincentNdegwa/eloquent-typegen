<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use VincentNdegwa\EloquentTypegen\Support\Scanners\MigrationScanner;

it('detects nullable columns in migrations', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-migrations-'.uniqid();
    $filesystem->makeDirectory($dir, 0755, true);

    $migration = <<<'PHP'
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('notes', function (Blueprint $table) {
    $table->id();
    $table->string('title')->nullable();
    $table->text('body');
    $table->timestamps()->nullable();
    $table->softDeletes();
});
PHP;

    $filesystem->put($dir.'/2026_05_05_000000_create_notes_table.php', $migration);

    $scanner = new MigrationScanner($filesystem);
    $result = $scanner->scan($dir);

    expect($result['nullable']['notes']['title'])->toBeTrue()
        ->and($result['nullable']['notes']['created_at'])->toBeTrue()
        ->and($result['nullable']['notes']['updated_at'])->toBeTrue()
        ->and($result['nullable']['notes']['deleted_at'])->toBeTrue()
        ->and($result['nullable']['notes']['body'] ?? false)->toBeFalse();

    $filesystem->deleteDirectory($dir);
});

it('detects column types from migrations', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-migrations-'.uniqid();
    $filesystem->makeDirectory($dir, 0755, true);

    $migration = <<<'PHP'
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->nullable();
    $table->string('title');
    $table->text('content');
    $table->boolean('published')->default(false);
    $table->json('meta')->nullable();
    $table->timestamps();
});
PHP;

    $filesystem->put($dir.'/2026_05_05_000000_create_posts_table.php', $migration);

    $scanner = new MigrationScanner($filesystem);
    $result = $scanner->scan($dir);

    expect($result['columnTypes']['posts']['id'])->toBe('number')
        ->and($result['columnTypes']['posts']['user_id'])->toBe('number')
        ->and($result['columnTypes']['posts']['title'])->toBe('string')
        ->and($result['columnTypes']['posts']['content'])->toBe('string')
        ->and($result['columnTypes']['posts']['published'])->toBe('boolean')
        ->and($result['columnTypes']['posts']['meta'])->toBe('json')
        ->and($result['columnTypes']['posts']['created_at'])->toBe('date')
        ->and($result['columnTypes']['posts']['updated_at'])->toBe('date');

    $filesystem->deleteDirectory($dir);
});

it('handles Schema::table migrations', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-migrations-'.uniqid();
    $filesystem->makeDirectory($dir, 0755, true);

    $migration = <<<'PHP'
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('users', function (Blueprint $table) {
    $table->unsignedBigInteger('active_version_id')->nullable()->after('parent_quote_id');
});
PHP;

    $filesystem->put($dir.'/2026_05_05_000000_add_active_version_to_users_table.php', $migration);

    $scanner = new MigrationScanner($filesystem);
    $result = $scanner->scan($dir);

    expect($result['columnTypes']['users']['active_version_id'])->toBe('number')
        ->and($result['nullable']['users']['active_version_id'])->toBeTrue();

    $filesystem->deleteDirectory($dir);
});

it('respects custom migration type map from config', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-migrations-'.uniqid();
    $filesystem->makeDirectory($dir, 0755, true);

    // Override 'string' to be 'number' (unusual but tests the override mechanism)
    config(['typegen.migration_type_map.string' => 'number']);

    $migration = <<<'PHP'
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->integer('count');
});
PHP;

    $filesystem->put($dir.'/2026_05_05_000000_create_posts_table.php', $migration);

    // Re-instantiate scanner with new config
    $scanner = new MigrationScanner($filesystem);
    $result = $scanner->scan($dir);

    expect($result['columnTypes']['posts']['id'])->toBe('number')
        ->and($result['columnTypes']['posts']['title'])->toBe('number') // Overridden
        ->and($result['columnTypes']['posts']['count'])->toBe('number');

    $filesystem->deleteDirectory($dir);

    // Reset config
    config(['typegen.migration_type_map.string' => 'string']);
});

it('allows adding custom type mappings', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-migrations-'.uniqid();
    $filesystem->makeDirectory($dir, 0755, true);

    // Add a custom mapping for a custom Blueprint method
    config(['typegen.migration_type_map' => [
        'id' => 'number',
        'string' => 'string',
        'customType' => 'CustomType',
    ]]);

    $migration = <<<'PHP'
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('items', function (Blueprint $table) {
    $table->id();
    $table->customType('field_name');
});
PHP;

    $filesystem->put($dir.'/2026_05_05_000000_create_items_table.php', $migration);

    $scanner = new MigrationScanner($filesystem);
    $result = $scanner->scan($dir);

    expect($result['columnTypes']['items']['field_name'])->toBe('CustomType');

    $filesystem->deleteDirectory($dir);
});

it('scans migrations in subdirectories recursively', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-migrations-'.uniqid();
    $filesystem->makeDirectory($dir.'/subdir', 0755, true);

    $migration = <<<'PHP'
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
});
PHP;

    $filesystem->put($dir.'/subdir/2026_05_05_000000_create_products_table.php', $migration);

    $scanner = new MigrationScanner($filesystem);
    $result = $scanner->scan($dir);

    expect($result['columnTypes']['products']['id'])->toBe('number')
        ->and($result['columnTypes']['products']['name'])->toBe('string');

    $filesystem->deleteDirectory($dir);
});

it('skips non-php files in migration directory', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-migrations-'.uniqid();
    $filesystem->makeDirectory($dir, 0755, true);

    $filesystem->put($dir.'/readme.md', 'This is a readme file');
    $filesystem->put($dir.'/2026_05_05_000000_create_notes_table.php', '<?php // valid migration');

    $scanner = new MigrationScanner($filesystem);
    $result = $scanner->scan($dir);

    // Should not crash, just return empty or valid results from PHP files
    expect($result)->toBeArray();

    $filesystem->deleteDirectory($dir);
});
