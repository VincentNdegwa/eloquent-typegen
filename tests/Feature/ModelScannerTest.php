<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use VincentNdegwa\EloquentTypegen\Support\Scanners\ModelScanner;

it('discovers models and relationships', function () {
    $filesystem = new Filesystem;

    $filesystem->ensureDirectoryExists(app_path('Models'));

    $filesystem->put(app_path('Models/ParentModel.php'), <<<'PHP'
<?php

namespace App\Models;

class ParentModel extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = ['name'];

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ChildModel::class);
    }
}
PHP);

    $filesystem->put(app_path('Models/ChildModel.php'), <<<'PHP'
<?php

namespace App\Models;

class ChildModel extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = ['parent_model_id', 'name'];
}
PHP);

    $scanner = new ModelScanner;
    $models = $scanner->scan();

    $parent = collect($models)->first(fn ($model) => $model->interfaceName === 'ParentModel');

    expect($parent)->not->toBeNull()
        ->and($parent?->relations)->not->toBeEmpty();

    $filesystem->delete(app_path('Models/ParentModel.php'));
    $filesystem->delete(app_path('Models/ChildModel.php'));
});

it('detects different relationship types', function () {
    $filesystem = new Filesystem;

    $filesystem->ensureDirectoryExists(app_path('Models'));

    $filesystem->put(app_path('Models/User.php'), <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Model
{
    protected $fillable = ['name', 'email'];

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
PHP);

    $filesystem->put(app_path('Models/Profile.php'), <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $fillable = ['user_id', 'bio'];
}
PHP);

    $filesystem->put(app_path('Models/Post.php'), <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = ['user_id', 'title'];
}
PHP);

    $filesystem->put(app_path('Models/Organization.php'), <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = ['name'];
}
PHP);

    $filesystem->put(app_path('Models/Role.php'), <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name'];
}
PHP);

    $scanner = new ModelScanner;
    $models = $scanner->scan();

    $user = collect($models)->first(fn ($model) => $model->interfaceName === 'User');

    expect($user)->not->toBeNull()
        ->and($user?->relations)->toHaveCount(4);

    $relationTypes = collect($user?->relations)->pluck('type');
    expect($relationTypes)->toContain('Profile')
        ->toContain('Post[]')
        ->toContain('Organization')
        ->toContain('Role[]');

    $filesystem->delete(app_path('Models/User.php'));
    $filesystem->delete(app_path('Models/Profile.php'));
    $filesystem->delete(app_path('Models/Post.php'));
    $filesystem->delete(app_path('Models/Organization.php'));
    $filesystem->delete(app_path('Models/Role.php'));
});

it('uses migration types as fallback when infer_types_from_migrations is true', function () {
    $filesystem = new Filesystem;

    // Create migration directory in the expected location
    $migrationDir = base_path('database/migrations');
    $filesystem->ensureDirectoryExists($migrationDir);

    $migration = <<<'PHP'
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('test_models', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->unsignedBigInteger('user_id')->nullable();
    $table->boolean('active')->default(false);
});
PHP;

    $filesystem->put($migrationDir.'/2026_05_05_000000_create_test_models_table.php', $migration);

    config([
        'typegen.migration_type_map' => [
            'id' => 'number',
            'string' => 'string',
            'unsignedBigInteger' => 'number',
            'boolean' => 'boolean',
        ],
        'typegen.infer_types_from_migrations' => true,
        'typegen.read_migrations' => true,
    ]);

    $filesystem->ensureDirectoryExists(app_path('Models'));

    $filesystem->put(app_path('Models/TestModel.php'), <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    protected $table = 'test_models';

    protected $fillable = ['name', 'user_id', 'active'];

    // No casts - types should come from migrations
}
PHP);

    $scanner = new ModelScanner;
    $models = $scanner->scan();

    $testModel = collect($models)->first(fn ($model) => $model->interfaceName === 'TestModel');

    expect($testModel)->not->toBeNull();
    assert($testModel !== null);

    $idField = collect($testModel->fields)->first(fn ($f) => $f->name === 'id');
    $nameField = collect($testModel->fields)->first(fn ($f) => $f->name === 'name');
    $userIdField = collect($testModel->fields)->first(fn ($f) => $f->name === 'user_id');
    $activeField = collect($testModel->fields)->first(fn ($f) => $f->name === 'active');

    assert($idField !== null);
    assert($nameField !== null);
    assert($userIdField !== null);
    assert($activeField !== null);

    expect($idField->type)->toBe('number')
        ->and($nameField->type)->toBe('string')
        ->and($userIdField->type)->toBe('number')
        ->and($activeField->type)->toBe('boolean')
        ->and($userIdField->nullable)->toBeTrue();

    $filesystem->delete(app_path('Models/TestModel.php'));
    $filesystem->delete($migrationDir.'/2026_05_05_000000_create_test_models_table.php');
});

it('does not use migration types when infer_types_from_migrations is false', function () {
    config([
        'typegen.infer_types_from_migrations' => false,
    ]);

    $filesystem = new Filesystem;
    $filesystem->ensureDirectoryExists(app_path('Models'));

    $filesystem->put(app_path('Models/TestModel2.php'), <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestModel2 extends Model
{
    protected $fillable = ['name', 'count'];

    // No casts - should be unknown without migration fallback
}
PHP);

    $scanner = new ModelScanner;
    $models = $scanner->scan();

    $testModel = collect($models)->first(fn ($model) => $model->interfaceName === 'TestModel2');

    expect($testModel)->not->toBeNull();
    assert($testModel !== null);

    $nameField = collect($testModel->fields)->first(fn ($f) => $f->name === 'name');
    $countField = collect($testModel->fields)->first(fn ($f) => $f->name === 'count');

    assert($nameField !== null);
    assert($countField !== null);

    // Should be unknown when migration fallback is disabled
    expect($nameField->type)->toBe('unknown')
        ->and($countField->type)->toBe('unknown');

    $filesystem->delete(app_path('Models/TestModel2.php'));
});

it('handles additional_models config', function () {
    config([
        'typegen.additional_models' => ['App\Models\CustomModel'],
        'typegen.model_paths' => [],
        'typegen.include_vendor_models' => false,
    ]);

    $scanner = new ModelScanner;
    $results = $scanner->scan();

    expect($results)->toBeEmpty(); // CustomModel doesn't exist, so empty
});

it('handles excluded_models config', function () {
    $filesystem = new Filesystem;
    $filesystem->ensureDirectoryExists(app_path('Models'));

    $filesystem->put(app_path('Models/ExcludedModel.php'), <<<'PHP'
<?php

namespace App\Models;

class ExcludedModel extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = ['name'];
}
PHP);

    config([
        'typegen.excluded_models' => ['App\Models\ExcludedModel'],
        'typegen.include_vendor_models' => false,
    ]);

    $scanner = new ModelScanner;
    $results = $scanner->scan();

    $excludedModel = collect($results)->first(fn ($model) => $model->className === 'App\Models\ExcludedModel');
    expect($excludedModel)->toBeNull();

    $filesystem->delete(app_path('Models/ExcludedModel.php'));
});
