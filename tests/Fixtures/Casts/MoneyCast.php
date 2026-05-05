<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Tests\Fixtures\Casts;

class MoneyCast
{
    public static function toTypeScript(): string
    {
        return '{ amount: number; currency: string }';
    }
}
