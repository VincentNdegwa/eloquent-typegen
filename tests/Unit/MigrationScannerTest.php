<?php

declare(strict_types=1);

use VincentNdegwa\EloquentTypegen\Support\Scanners\MigrationScanner;
use Illuminate\Filesystem\Filesystem;

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

    expect($result['notes']['title'])->toBeTrue()
        ->and($result['notes']['created_at'])->toBeTrue()
        ->and($result['notes']['updated_at'])->toBeTrue()
        ->and($result['notes']['deleted_at'])->toBeTrue()
        ->and($result['notes']['body'] ?? false)->toBeFalse();

    $filesystem->deleteDirectory($dir);
});
