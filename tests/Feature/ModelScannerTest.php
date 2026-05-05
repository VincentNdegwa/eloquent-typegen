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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParentModel extends Model
{
    protected $fillable = ['name'];

    public function children(): HasMany
    {
        return $this->hasMany(ChildModel::class);
    }
}
PHP);

    $filesystem->put(app_path('Models/ChildModel.php'), <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChildModel extends Model
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
