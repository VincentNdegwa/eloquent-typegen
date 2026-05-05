<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

it('generates typescript files for a model', function () {
    $filesystem = new Filesystem();

    $filesystem->ensureDirectoryExists(app_path('Models'));
    $filesystem->ensureDirectoryExists(base_path('database/migrations'));

    $filesystem->put(app_path('Models/Article.php'), <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = ['title'];

    protected $casts = [
        'title' => 'string',
        'published_at' => 'datetime',
    ];
}
PHP);

    $filesystem->put(base_path('database/migrations/2026_05_05_000010_create_articles_table.php'), <<<'PHP'
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('articles', function (Blueprint $table) {
    $table->id();
    $table->string('title')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
});
PHP);

    $this->artisan('typegen:generate')->assertSuccessful();

    $output = base_path('tests-output/article.ts');
    expect($filesystem->exists($output))->toBeTrue();

    $content = $filesystem->get($output);
    expect($content)
        ->toContain('export interface Article')
        ->toContain('title?: Nullable<string>')
        ->toContain('published_at?: Nullable<string>');

    $filesystem->deleteDirectory(base_path('tests-output'));
    $filesystem->delete(app_path('Models/Article.php'));
    $filesystem->delete(base_path('database/migrations/2026_05_05_000010_create_articles_table.php'));
});
