<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Tests\Unit;

use VincentNdegwa\EloquentTypegen\Support\Generators\ResourceGenerator;
use VincentNdegwa\EloquentTypegen\Support\Metadata\FieldMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\ModelMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\ResourceFieldMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\ResourceMetadata;

it('generates resource type with Resource suffix', function () {
    $resource = new ResourceMetadata(
        className: 'App\\Http\\Resources\\PostResource',
        interfaceName: 'PostResource',
        fileName: 'post-resource.ts',
        relatedModel: 'Post',
    );
    $resource->fields = [
        new ResourceFieldMetadata('id', 'number', false, false, false),
        new ResourceFieldMetadata('title', 'string', false, false, false),
    ];

    $generator = new ResourceGenerator(sys_get_temp_dir().'/test-resources');
    $files = $generator->generate([$resource]);

    expect($files)->toHaveCount(2);
    expect($files)->toHaveKey(sys_get_temp_dir().'/test-resources/post-resource.ts');
    expect($files)->toHaveKey(sys_get_temp_dir().'/test-resources/index.ts');

    $content = $files[sys_get_temp_dir().'/test-resources/post-resource.ts'];
    expect($content)->toContain('export interface PostResource');
});

it('extends model type when model exists', function () {
    $model = new ModelMetadata(
        className: 'App\\Models\\Post',
        interfaceName: 'Post',
        fileName: 'post.ts',
    );
    $model->fields = [
        new FieldMetadata('id', 'number', false, false),
        new FieldMetadata('title', 'string', false, false),
        new FieldMetadata('user_id', 'number', false, false),
    ];

    $resource = new ResourceMetadata(
        className: 'App\\Http\\Resources\\PostResource',
        interfaceName: 'PostResource',
        fileName: 'post-resource.ts',
        relatedModel: 'Post',
    );
    $resource->fields = [
        new ResourceFieldMetadata('id', 'number', false, false, false),
        new ResourceFieldMetadata('title', 'string', false, false, false),
        new ResourceFieldMetadata('author', 'UserResource', false, false, false),
    ];

    $modelMap = ['Post' => $model];
    $generator = new ResourceGenerator(sys_get_temp_dir().'/test-resources', $modelMap);
    $files = $generator->generate([$resource]);

    $content = $files[sys_get_temp_dir().'/test-resources/post-resource.ts'];
    expect($content)->toContain('extends Omit<Post, \'user_id\'>');
    expect($content)->toContain('import type { Post } from');
    expect($content)->toContain('author: UserResource');
});

it('does not extend when model does not exist', function () {
    $resource = new ResourceMetadata(
        'App\\Http\\Resources\\PostResource',
        'PostResource',
        'post-resource.ts',
        [],
        [],
        'NonExistentModel',
    );

    $resource->fields[] = new ResourceFieldMetadata('id', 'number', false, false, false);
    $resource->fields[] = new ResourceFieldMetadata('title', 'string', false, false, false);

    $generator = new ResourceGenerator('/tmp/types', []);
    $files = $generator->generate([$resource]);

    expect($files['/tmp/types/post-resource.ts'])->toContain('export interface PostResource {');
    expect($files['/tmp/types/post-resource.ts'])->not->toContain('extends');
});

it('generates index.ts barrel file', function () {
    $model = new ModelMetadata('App\\Models\\User', 'User', 'user.ts');
    $model->fields[] = new FieldMetadata('id', 'number', false, true, false);

    $resource = new ResourceMetadata(
        'App\\Http\\Resources\\UserResource',
        'UserResource',
        'user-resource.ts',
        [],
        [],
        'User',
    );
    $resource->fields[] = new ResourceFieldMetadata('id', 'number', false, false, false);

    $generator = new ResourceGenerator('/tmp/types', ['User' => $model]);
    $files = $generator->generate([$resource]);

    expect($files)->toHaveKey('/tmp/types/index.ts');
    expect($files['/tmp/types/index.ts'])->toContain('export type { UserResource } from \'./user-resource\';');
});

it('does not generate index.ts when generate_index is false', function () {
    config(['typegen.generate_index' => false]);

    $model = new ModelMetadata('App\\Models\\User', 'User', 'user.ts');
    $model->fields[] = new FieldMetadata('id', 'number', false, true, false);

    $resource = new ResourceMetadata(
        'App\\Http\\Resources\\UserResource',
        'UserResource',
        'user-resource.ts',
        [],
        [],
        'User',
    );
    $resource->fields[] = new ResourceFieldMetadata('id', 'number', false, false, false);

    $generator = new ResourceGenerator('/tmp/types', ['User' => $model]);
    $files = $generator->generate([$resource]);

    expect($files)->not->toHaveKey('/tmp/types/index.ts');
});
