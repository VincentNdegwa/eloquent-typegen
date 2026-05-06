<?php

declare(strict_types=1);

use VincentNdegwa\EloquentTypegen\Support\Generators\FormRequestGenerator;
use VincentNdegwa\EloquentTypegen\Support\Metadata\FormRequestFieldMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\FormRequestMetadata;

it('generates form request type', function () {
    $request = new FormRequestMetadata(
        'App\\Http\\Requests\\StoreUserRequest',
        'StoreUserRequest',
        'store-user-request.ts',
    );

    $request->fields[] = new FormRequestFieldMetadata('name', 'string', false, false, null);
    $request->fields[] = new FormRequestFieldMetadata('email', 'string', false, false, null);

    $generator = new FormRequestGenerator('/tmp/types');
    $files = $generator->generate([$request]);

    expect($files)->toHaveCount(2);
    expect($files['/tmp/types/store-user-request.ts'])->toContain('export interface StoreUserRequest {');
    expect($files['/tmp/types/store-user-request.ts'])->toContain('name: string;');
    expect($files['/tmp/types/store-user-request.ts'])->toContain('email: string;');
    expect($files['/tmp/types/index.ts'])->toContain('export type { StoreUserRequest } from \'./store-user-request\';');
});

it('handles nullable fields', function () {
    $request = new FormRequestMetadata(
        'App\\Http\\Requests\\StoreUserRequest',
        'StoreUserRequest',
        'store-user-request.ts',
    );

    $request->fields[] = new FormRequestFieldMetadata('bio', 'string', false, true, null);

    $generator = new FormRequestGenerator('/tmp/types');
    $files = $generator->generate([$request]);

    expect($files['/tmp/types/store-user-request.ts'])->toContain('bio: Nullable<string>;');
});

it('handles optional fields', function () {
    $request = new FormRequestMetadata(
        'App\\Http\\Requests\\StoreUserRequest',
        'StoreUserRequest',
        'store-user-request.ts',
    );

    $request->fields[] = new FormRequestFieldMetadata('nickname', 'string', true, false, null);

    $generator = new FormRequestGenerator('/tmp/types');
    $files = $generator->generate([$request]);

    expect($files['/tmp/types/store-user-request.ts'])->toContain('    nickname?: string;');
});

it('handles nullable and optional fields', function () {
    $request = new FormRequestMetadata(
        'App\\Http\\Requests\\StoreUserRequest',
        'StoreUserRequest',
        'store-user-request.ts',
    );

    $request->fields[] = new FormRequestFieldMetadata('bio', 'string', true, true, null);

    $generator = new FormRequestGenerator('/tmp/types');
    $files = $generator->generate([$request]);

    expect($files['/tmp/types/store-user-request.ts'])->toContain('bio?: Nullable<string>;');
});

it('handles conditional rule comments', function () {
    $request = new FormRequestMetadata(
        'App\\Http\\Requests\\StoreUserRequest',
        'StoreUserRequest',
        'store-user-request.ts',
    );

    $request->fields[] = new FormRequestFieldMetadata('status', 'string', false, false, 'sometimes|required');

    $generator = new FormRequestGenerator('/tmp/types');
    $files = $generator->generate([$request]);

    expect($files['/tmp/types/store-user-request.ts'])->toContain('status: string;  // sometimes|required');
});

it('generates index.ts barrel file', function () {
    $request1 = new FormRequestMetadata(
        'App\\Http\\Requests\\StoreUserRequest',
        'StoreUserRequest',
        'store-user-request.ts',
    );
    $request2 = new FormRequestMetadata(
        'App\\Http\\Requests\\UpdateUserRequest',
        'UpdateUserRequest',
        'update-user-request.ts',
    );

    $request1->fields[] = new FormRequestFieldMetadata('name', 'string', false, false, null);
    $request2->fields[] = new FormRequestFieldMetadata('name', 'string', false, false, null);

    $generator = new FormRequestGenerator('/tmp/types');
    $files = $generator->generate([$request1, $request2]);

    expect($files['/tmp/types/index.ts'])->toContain('export type { StoreUserRequest } from \'./store-user-request\';');
    expect($files['/tmp/types/index.ts'])->toContain('export type { UpdateUserRequest } from \'./update-user-request\';');
});

it('does not generate index.ts when generate_index is false', function () {
    config(['typegen.generate_index' => false]);

    $request = new FormRequestMetadata(
        'App\\Http\\Requests\\StoreUserRequest',
        'StoreUserRequest',
        'store-user-request.ts',
    );

    $request->fields[] = new FormRequestFieldMetadata('name', 'string', false, false, null);

    $generator = new FormRequestGenerator('/tmp/types');
    $files = $generator->generate([$request]);

    expect($files)->not->toHaveKey('/tmp/types/index.ts');
});

it('resolves absolute output path', function () {
    $request = new FormRequestMetadata(
        'App\\Http\\Requests\\StoreUserRequest',
        'StoreUserRequest',
        'store-user-request.ts',
    );

    $request->fields[] = new FormRequestFieldMetadata('name', 'string', false, false, null);

    $generator = new FormRequestGenerator('/absolute/path/types');
    $files = $generator->generate([$request]);

    expect($files)->toHaveKey('/absolute/path/types/store-user-request.ts');
});

it('resolves relative output path', function () {
    $request = new FormRequestMetadata(
        'App\\Http\\Requests\\StoreUserRequest',
        'StoreUserRequest',
        'store-user-request.ts',
    );

    $request->fields[] = new FormRequestFieldMetadata('name', 'string', false, false, null);

    $generator = new FormRequestGenerator('resources/js/types');
    $files = $generator->generate([$request]);

    expect($files)->toHaveKey(base_path('resources/js/types/store-user-request.ts'));
});
