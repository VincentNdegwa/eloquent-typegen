<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use VincentNdegwa\EloquentTypegen\Support\Metadata\FieldMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\ModelMetadata;
use VincentNdegwa\EloquentTypegen\Support\Scanners\ResourceScanner;

beforeEach(function () {
    // Reset config
    config(['typegen.resource_paths' => ['Http/Resources']]);
});

it('scans resource files and extracts toArray method', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    $resource = <<<'PHP'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Resources/UserResource.php', $resource);

    config(['typegen.resource_paths' => ['Http/Resources']]);

    $scanner = new ResourceScanner($dir);
    $resources = $scanner->scan();

    expect($resources)->toHaveCount(1);
    expect($resources[0]->className)->toBe('App\\Http\\Resources\\UserResource');
    expect($resources[0]->interfaceName)->toBe('UserResource');
    expect($resources[0]->fileName)->toBe('user-resource.ts');
    expect($resources[0]->fields)->toHaveCount(3);

    $filesystem->deleteDirectory($dir);
});

it('detects conditional fields from when() calls', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    $resource = <<<'PHP'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->when($request->user()->isAdmin(), $this->email),
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Resources/UserResource.php', $resource);

    config(['typegen.resource_paths' => ['Http/Resources']]);

    $scanner = new ResourceScanner($dir);
    $resources = $scanner->scan();

    expect($resources[0]->fields[1]->name)->toBe('email');
    expect($resources[0]->fields[1]->conditional)->toBeTrue();
    expect($resources[0]->fields[1]->optional)->toBeTrue();
    expect($resources[0]->fields[1]->condition)->toBe('when()');

    $filesystem->deleteDirectory($dir);
});

it('detects when() with closure callback', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    $resource = <<<'PHP'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'email' => $this->when($isAdmin, fn() => $this->email),
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Resources/UserResource.php', $resource);

    config(['typegen.resource_paths' => ['Http/Resources']]);

    $scanner = new ResourceScanner($dir);
    $resources = $scanner->scan();

    expect($resources[0]->fields[0]->name)->toBe('email');
    expect($resources[0]->fields[0]->conditional)->toBeTrue();
    expect($resources[0]->fields[0]->optional)->toBeTrue();
    expect($resources[0]->fields[0]->condition)->toBe('when()');

    $filesystem->deleteDirectory($dir);
});

it('detects whenLoaded() calls', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    $resource = <<<'PHP'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'posts' => PostResource::collection($this->whenLoaded('posts')),
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Resources/UserResource.php', $resource);

    config(['typegen.resource_paths' => ['Http/Resources']]);

    $scanner = new ResourceScanner($dir);
    $resources = $scanner->scan();

    expect($resources[0]->fields[1]->name)->toBe('posts');
    expect($resources[0]->fields[1]->conditional)->toBeTrue();
    expect($resources[0]->fields[1]->condition)->toBe('whenLoaded: posts');

    $filesystem->deleteDirectory($dir);
});

it('detects whenLoaded() with closure callback', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    $resource = <<<'PHP'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'role' => $this->whenLoaded('role', fn() => new RoleResource($this->role)),
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Resources/UserResource.php', $resource);

    config(['typegen.resource_paths' => ['Http/Resources']]);

    $scanner = new ResourceScanner($dir);
    $resources = $scanner->scan();

    expect($resources[0]->fields[0]->name)->toBe('role');
    expect($resources[0]->fields[0]->conditional)->toBeTrue();
    expect($resources[0]->fields[0]->condition)->toBe('whenLoaded: role');
    expect($resources[0]->fields[0]->nestedResource)->toBe('RoleResource');
    expect($resources[0]->fields[0]->type)->toBe('RoleResource');

    $filesystem->deleteDirectory($dir);
});

it('detects nested resource instantiation', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    $resource = <<<'PHP'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'profile' => new ProfileResource($this->profile),
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Resources/UserResource.php', $resource);

    config(['typegen.resource_paths' => ['Http/Resources']]);

    $scanner = new ResourceScanner($dir);
    $resources = $scanner->scan();

    expect($resources[0]->fields[1]->name)->toBe('profile');
    expect($resources[0]->fields[1]->nestedResource)->toBe('ProfileResource');
    expect($resources[0]->fields[1]->type)->toBe('ProfileResource');

    $filesystem->deleteDirectory($dir);
});

it('handles mergeWhen() spread expressions', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    $resource = <<<'PHP'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            ...$this->mergeWhen($isAdmin, [
                'admin_notes' => $this->notes,
            ]),
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Resources/UserResource.php', $resource);

    config(['typegen.resource_paths' => ['Http/Resources']]);

    $scanner = new ResourceScanner($dir);
    $resources = $scanner->scan();

    expect($resources[0]->fields)->toHaveCount(2);
    expect($resources[0]->fields[1]->name)->toBe('admin_notes');
    expect($resources[0]->fields[1]->conditional)->toBeTrue();
    expect($resources[0]->fields[1]->optional)->toBeTrue();
    expect($resources[0]->fields[1]->condition)->toBe('mergeWhen()');

    $filesystem->deleteDirectory($dir);
});

it('handles merge() spread expressions', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    $resource = <<<'PHP'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            ...$this->merge([
                'extra' => $this->extra,
            ]),
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Resources/UserResource.php', $resource);

    config(['typegen.resource_paths' => ['Http/Resources']]);

    $scanner = new ResourceScanner($dir);
    $resources = $scanner->scan();

    expect($resources[0]->fields)->toHaveCount(2);
    expect($resources[0]->fields[1]->name)->toBe('extra');
    expect($resources[0]->fields[1]->conditional)->toBeFalse();

    $filesystem->deleteDirectory($dir);
});

it('handles array_merge in return statement', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    $resource = <<<'PHP'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'extra' => $this->extra,
        ]);
    }
}
PHP;

    $filesystem->put($dir.'/Http/Resources/UserResource.php', $resource);

    config(['typegen.resource_paths' => ['Http/Resources']]);

    $scanner = new ResourceScanner($dir);
    $resources = $scanner->scan();

    expect($resources[0]->fields)->toHaveCount(1);
    expect($resources[0]->fields[0]->name)->toBe('extra');

    $filesystem->deleteDirectory($dir);
});

it('detects nullsafe method calls as nullable', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    $resource = <<<'PHP'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'deleted_at' => $this->deleted_at?->format('Y-m-d'),
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Resources/UserResource.php', $resource);

    config(['typegen.resource_paths' => ['Http/Resources']]);

    $scanner = new ResourceScanner($dir);
    $resources = $scanner->scan();

    expect($resources[0]->fields[0]->name)->toBe('deleted_at');
    expect($resources[0]->fields[0]->nullable)->toBeTrue();
    expect($resources[0]->fields[0]->type)->toBe('string');

    $filesystem->deleteDirectory($dir);
});

it('handles common PHP function calls', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    $resource = <<<'PHP'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'upper_name' => strtoupper($this->name),
            'url' => route('users.show', $this),
            'count' => count($this->items),
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Resources/UserResource.php', $resource);

    config(['typegen.resource_paths' => ['Http/Resources']]);

    $scanner = new ResourceScanner($dir);
    $resources = $scanner->scan();

    expect($resources[0]->fields[0]->type)->toBe('string');
    expect($resources[0]->fields[1]->type)->toBe('string');
    expect($resources[0]->fields[2]->type)->toBe('number');

    $filesystem->deleteDirectory($dir);
});

it('resolves $this->property types from model metadata', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    $resource = <<<'PHP'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Resources/UserResource.php', $resource);

    config(['typegen.resource_paths' => ['Http/Resources']]);

    // Create model metadata
    $userModel = new ModelMetadata('App\\Models\\User', 'User', 'user.ts');
    $userModel->fields[] = new FieldMetadata('id', 'number', false, true, false);
    $userModel->fields[] = new FieldMetadata('name', 'string', false, false, false);

    $scanner = new ResourceScanner($dir);
    $scanner->withModels([$userModel]);
    $resources = $scanner->scan();

    expect($resources[0]->fields[0]->type)->toBe('number');
    expect($resources[0]->fields[1]->type)->toBe('string');

    $filesystem->deleteDirectory($dir);
});

it('only matches classes extending JsonResource or Resource', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    // A class that doesn't extend JsonResource should be skipped
    $resource = <<<'PHP'
<?php

namespace App\Http\Resources;

class OtherClass
{
    public function toArray($request): array
    {
        return [];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Resources/OtherClass.php', $resource);

    config(['typegen.resource_paths' => ['Http/Resources']]);

    $scanner = new ResourceScanner($dir);
    $resources = $scanner->scan();

    expect($resources)->toBeEmpty();

    $filesystem->deleteDirectory($dir);
});

it('skips files that cannot be parsed', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-resources-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Resources', 0755, true);

    $filesystem->put($dir.'/Http/Resources/Invalid.php', '<?php invalid php code');

    config(['typegen.resource_paths' => ['Http/Resources']]);

    $scanner = new ResourceScanner($dir);
    $resources = $scanner->scan();

    expect($resources)->toBeEmpty();

    $filesystem->deleteDirectory($dir);
});
