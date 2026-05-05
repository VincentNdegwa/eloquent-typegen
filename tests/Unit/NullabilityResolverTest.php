<?php

declare(strict_types=1);

use Based\EloquentTypegen\Support\Resolvers\NullabilityResolver;

it('combines migration data with special columns', function () {
    $resolver = new NullabilityResolver(false);

    // Test special columns that are always nullable
    expect($resolver->isNullable('users', 'deleted_at'))->toBeTrue()
        ->and($resolver->isNullable('users', 'remember_token'))->toBeTrue();
});

it('returns false for regular columns when migrations disabled', function () {
    $resolver = new NullabilityResolver(false);

    expect($resolver->isNullable('users', 'name'))->toBeFalse()
        ->and($resolver->isNullable('users', 'email'))->toBeFalse();
});
