<?php

declare(strict_types=1);

use Based\EloquentTypegen\Support\Scanners\ModelScanner;
use Illuminate\Filesystem\Filesystem;

it('discovers models and relationships', function () {
    $filesystem = new Filesystem();

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

    $scanner = new ModelScanner();
    $models = $scanner->scan();

    $parent = collect($models)->first(fn ($model) => $model->interfaceName === 'ParentModel');

    expect($parent)->not->toBeNull()
        ->and($parent->relations)->not->toBeEmpty();

    $filesystem->delete(app_path('Models/ParentModel.php'));
    $filesystem->delete(app_path('Models/ChildModel.php'));
});


it('detects different relationship types', function () {
    $filesystem = new Filesystem();

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

    $scanner = new ModelScanner();
    $models = $scanner->scan();

    $user = collect($models)->first(fn ($model) => $model->interfaceName === 'User');

    expect($user)->not->toBeNull()
        ->and($user->relations)->toHaveCount(4);

    $relationTypes = collect($user->relations)->pluck('type');
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
