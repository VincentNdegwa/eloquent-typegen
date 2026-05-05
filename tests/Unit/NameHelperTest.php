<?php

declare(strict_types=1);

use VincentNdegwa\EloquentTypegen\Support\Helpers\NameHelper;

it('converts model class name to interface name', function () {
    expect(NameHelper::modelToInterface('App\\Models\\User'))->toBe('User')
        ->and(NameHelper::modelToInterface('App\\Models\\BlogPost'))->toBe('BlogPost');
});

it('converts model class name to file name', function () {
    expect(NameHelper::modelToFileName('App\\Models\\User'))->toBe('user.ts')
        ->and(NameHelper::modelToFileName('App\\Models\\BlogPost'))->toBe('blog-post.ts')
        ->and(NameHelper::modelToFileName('App\\Models\\APIResponse'))->toBe('a-p-i-response.ts');
});

it('converts accessor method name to field name', function () {
    expect(NameHelper::accessorToField('fullName'))->toBe('full_name')
        ->and(NameHelper::accessorToField('isActive'))->toBe('is_active')
        ->and(NameHelper::accessorToField('totalCount'))->toBe('total_count');
});
