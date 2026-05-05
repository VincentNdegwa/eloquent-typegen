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
