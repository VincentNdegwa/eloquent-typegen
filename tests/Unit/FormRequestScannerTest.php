<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use VincentNdegwa\EloquentTypegen\Support\Scanners\FormRequestScanner;

beforeEach(function () {
    // Reset config
    config(['typegen.request_paths' => ['Http/Requests']]);
    config(['typegen.date_type' => 'string']);
});

it('scans form request files and extracts rules', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-requests-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Requests', 0755, true);

    $request = <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'nullable|integer',
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Requests/StoreUserRequest.php', $request);

    config(['typegen.request_paths' => ['Http/Requests']]);

    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();

    expect($requests)->toHaveCount(1);
    expect($requests[0]->className)->toBe('App\\Http\\Requests\\StoreUserRequest');
    expect($requests[0]->interfaceName)->toBe('StoreUserRequest');
    expect($requests[0]->fileName)->toBe('store-user-request.ts');
    expect($requests[0]->fields)->toHaveCount(3);

    $filesystem->deleteDirectory($dir);
});

it('detects required fields', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-requests-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Requests', 0755, true);

    $request = <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Requests/StoreUserRequest.php', $request);

    config(['typegen.request_paths' => ['Http/Requests']]);

    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();

    expect($requests[0]->fields[0]->name)->toBe('name');
    expect($requests[0]->fields[0]->optional)->toBeFalse();
    expect($requests[0]->fields[0]->nullable)->toBeFalse();
    expect($requests[0]->fields[0]->type)->toBe('string');

    $filesystem->deleteDirectory($dir);
});

it('detects nullable fields separately from optional', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-requests-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Requests', 0755, true);

    $request = <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'age' => 'nullable|integer',
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Requests/StoreUserRequest.php', $request);

    config(['typegen.request_paths' => ['Http/Requests']]);

    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();

    expect($requests[0]->fields[0]->name)->toBe('age');
    expect($requests[0]->fields[0]->nullable)->toBeTrue();
    expect($requests[0]->fields[0]->optional)->toBeTrue(); // nullable defaults to optional
    expect($requests[0]->fields[0]->type)->toBe('number');

    $filesystem->deleteDirectory($dir);
});

it('handles required|nullable pattern', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-requests-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Requests', 0755, true);

    $request = <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'bio' => 'required|nullable|string',
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Requests/StoreUserRequest.php', $request);

    config(['typegen.request_paths' => ['Http/Requests']]);

    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();

    expect($requests[0]->fields[0]->name)->toBe('bio');
    expect($requests[0]->fields[0]->nullable)->toBeTrue();
    expect($requests[0]->fields[0]->optional)->toBeFalse(); // required makes it non-optional
    expect($requests[0]->fields[0]->type)->toBe('string');

    $filesystem->deleteDirectory($dir);
});

it('handles sometimes rule', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-requests-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Requests', 0755, true);

    $request = <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nickname' => 'sometimes|string',
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Requests/StoreUserRequest.php', $request);

    config(['typegen.request_paths' => ['Http/Requests']]);

    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();

    expect($requests[0]->fields[0]->name)->toBe('nickname');
    expect($requests[0]->fields[0]->optional)->toBeTrue();
    expect($requests[0]->fields[0]->nullable)->toBeFalse();
    expect($requests[0]->fields[0]->comment)->toBe('conditional: sometimes');

    $filesystem->deleteDirectory($dir);
});

it('maps validation rules to types', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-requests-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Requests', 0755, true);

    $request = <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'count' => 'required|integer',
            'active' => 'required|boolean',
            'tags' => 'array',
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Requests/StoreUserRequest.php', $request);

    config(['typegen.request_paths' => ['Http/Requests']]);

    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();

    expect($requests[0]->fields[0]->type)->toBe('string'); // email
    expect($requests[0]->fields[1]->type)->toBe('number'); // integer
    expect($requests[0]->fields[2]->type)->toBe('boolean'); // boolean
    expect($requests[0]->fields[3]->type)->toBe('unknown[]'); // array

    $filesystem->deleteDirectory($dir);
});

it('handles in: rule as union type', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-requests-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Requests', 0755, true);

    $request = <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'required|in:active,inactive,pending',
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Requests/StoreUserRequest.php', $request);

    config(['typegen.request_paths' => ['Http/Requests']]);

    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();

    expect($requests[0]->fields[0]->type)->toBe("'active' | 'inactive' | 'pending'");

    $filesystem->deleteDirectory($dir);
});

it('handles array rules syntax', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-requests-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Requests', 0755, true);

    $request = <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'age' => ['nullable', 'integer'],
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Requests/StoreUserRequest.php', $request);

    config(['typegen.request_paths' => ['Http/Requests']]);

    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();

    expect($requests[0]->fields[0]->name)->toBe('email');
    expect($requests[0]->fields[0]->type)->toBe('string');
    expect($requests[0]->fields[0]->optional)->toBeFalse();

    expect($requests[0]->fields[1]->name)->toBe('age');
    expect($requests[0]->fields[1]->type)->toBe('number');
    expect($requests[0]->fields[1]->nullable)->toBeTrue();

    $filesystem->deleteDirectory($dir);
});

it('resolves wildcard fields', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-requests-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Requests', 0755, true);

    $request = <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tags' => 'array',
            'tags.*' => 'string',
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Requests/StoreUserRequest.php', $request);

    config(['typegen.request_paths' => ['Http/Requests']]);

    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();

    expect($requests[0]->fields)->toHaveCount(1); // tags.* is dropped
    expect($requests[0]->fields[0]->name)->toBe('tags');
    expect($requests[0]->fields[0]->type)->toBe('string[]'); // refined from unknown[]

    $filesystem->deleteDirectory($dir);
});

it('adds confirmation field for confirmed rule', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-requests-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Requests', 0755, true);

    $request = <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => 'required|string|confirmed',
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Requests/StoreUserRequest.php', $request);

    config(['typegen.request_paths' => ['Http/Requests']]);

    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();

    expect($requests[0]->fields)->toHaveCount(2);
    expect($requests[0]->fields[0]->name)->toBe('password');
    expect($requests[0]->fields[1]->name)->toBe('password_confirmation');
    expect($requests[0]->fields[1]->comment)->toBe('confirmation for password');

    $filesystem->deleteDirectory($dir);
});

it('respects date_type config', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-requests-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Requests', 0755, true);

    $request = <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'published_at' => 'required|date',
        ];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Requests/StoreUserRequest.php', $request);

    config(['typegen.request_paths' => ['Http/Requests']]);

    // Test with Date type
    config(['typegen.date_type' => 'Date']);
    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();
    expect($requests[0]->fields[0]->type)->toBe('Date');

    // Test with string type
    config(['typegen.date_type' => 'string']);
    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();
    expect($requests[0]->fields[0]->type)->toBe('string');

    $filesystem->deleteDirectory($dir);
});

it('only matches classes extending FormRequest', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-requests-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Requests', 0755, true);

    // A class that doesn't extend FormRequest should be skipped
    $request = <<<'PHP'
<?php

namespace App\Http\Requests;

class OtherRequest
{
    public function rules(): array
    {
        return [];
    }
}
PHP;

    $filesystem->put($dir.'/Http/Requests/OtherRequest.php', $request);

    config(['typegen.request_paths' => ['Http/Requests']]);

    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();

    expect($requests)->toBeEmpty();

    $filesystem->deleteDirectory($dir);
});

it('skips non-php files', function () {
    $filesystem = new Filesystem;
    $dir = sys_get_temp_dir().'/eloquent-typegen-requests-'.uniqid();
    $filesystem->makeDirectory($dir.'/Http/Requests', 0755, true);

    $filesystem->put($dir.'/Http/Requests/OtherClass.txt', 'not a php file');

    config(['typegen.request_paths' => ['Http/Requests']]);

    $scanner = new FormRequestScanner($dir);
    $requests = $scanner->scan();

    expect($requests)->toBeEmpty();

    $filesystem->deleteDirectory($dir);
});
