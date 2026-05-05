<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Helpers;

use Illuminate\Support\Str;

class NameHelper
{
    public static function modelToInterface(string $className): string
    {
        return class_basename($className);
    }

    public static function modelToFileName(string $className): string
    {
        return Str::kebab(class_basename($className)).'.ts';
    }

    public static function accessorToField(string $methodName): string
    {
        return Str::snake($methodName);
    }
}
