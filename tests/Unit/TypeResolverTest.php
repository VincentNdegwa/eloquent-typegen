<?php

declare(strict_types=1);

use Based\EloquentTypegen\Support\Resolvers\TypeResolver;
use Based\EloquentTypegen\Tests\Fixtures\Enums\IntStatus;
use Based\EloquentTypegen\Tests\Fixtures\Enums\StringStatus;
use Based\EloquentTypegen\Tests\Fixtures\Enums\UnitStatus;

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
