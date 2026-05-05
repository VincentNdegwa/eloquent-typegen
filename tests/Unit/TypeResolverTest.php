<?php

declare(strict_types=1);

use VincentNdegwa\EloquentTypegen\Support\Resolvers\TypeResolver;
use VincentNdegwa\EloquentTypegen\Tests\Fixtures\Casts\MoneyCast;
use VincentNdegwa\EloquentTypegen\Tests\Fixtures\Enums\IntStatus;
use VincentNdegwa\EloquentTypegen\Tests\Fixtures\Enums\StringStatus;
use VincentNdegwa\EloquentTypegen\Tests\Fixtures\Enums\UnitStatus;

it('resolves scalar casts', function () {
    $resolver = new TypeResolver('string');

    expect($resolver->resolve('boolean')->type)->toBe('boolean')
        ->and($resolver->resolve('int')->type)->toBe('number')
        ->and($resolver->resolve('json')->type)->toBe('Record<string, unknown>')
        ->and($resolver->resolve('date')->type)->toBe('string');
});

it('resolves string backed enums', function () {
    $resolver = new TypeResolver('string');
    $resolution = $resolver->resolve(StringStatus::class);

    expect($resolution->type)->toBe('StringStatus')
        ->and($resolution->enum)->not->toBeNull()
        ->and($resolution->enum?->definition)->toBe("'draft' | 'published'");
});

it('resolves int backed enums', function () {
    $resolver = new TypeResolver('string');
    $resolution = $resolver->resolve(IntStatus::class);

    expect($resolution->type)->toBe('IntStatus')
        ->and($resolution->enum?->definition)->toBe('1 | 2');
});

it('resolves unit enums', function () {
    $resolver = new TypeResolver('string');
    $resolution = $resolver->resolve(UnitStatus::class);

    expect($resolution->type)->toBe('UnitStatus')
        ->and($resolution->enum?->definition)->toBe("'Alpha' | 'Beta'");
});

it('resolves custom cast with toTypeScript method', function () {
    $resolver = new TypeResolver('string');
    $resolution = $resolver->resolve(MoneyCast::class);

    expect($resolution->type)->toBe('{ amount: number; currency: string }')
        ->and($resolution->enum)->toBeNull();
});

it('resolves custom type map from config', function () {
    $customMap = [
        'App\\Casts\\CustomMoney' => '{ amount: number; currency: string }',
    ];
    $resolver = new TypeResolver('string', $customMap);

    expect($resolver->resolve('App\\Casts\\CustomMoney')->type)->toBe('{ amount: number; currency: string }');
});

it('resolves AsCollection cast', function () {
    $resolver = new TypeResolver('string');
    expect($resolver->resolve('AsCollection')->type)->toBe('unknown[]');
});

it('resolves AsArrayObject cast', function () {
    $resolver = new TypeResolver('string');
    expect($resolver->resolve('AsArrayObject')->type)->toBe('unknown[]');
});

it('resolves AsStringable cast', function () {
    $resolver = new TypeResolver('string');
    expect($resolver->resolve('AsStringable')->type)->toBe('string');
});

it('resolves AsEnumCollection cast', function () {
    $resolver = new TypeResolver('string');
    $resolution = $resolver->resolve('AsEnumCollection:'.StringStatus::class);

    expect($resolution->type)->toBe('StringStatus[]')
        ->and($resolution->enum?->definition)->toBe("'draft' | 'published'");
});

it('resolves decimal with precision', function () {
    $resolver = new TypeResolver('string');
    expect($resolver->resolve('decimal:2')->type)->toBe('number');
});

it('returns unknown for unhandled cast types', function () {
    $resolver = new TypeResolver('string');
    expect($resolver->resolve('SomeUnknownCast')->type)->toBe('unknown');
});
