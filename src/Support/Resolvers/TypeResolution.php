<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Resolvers;

use VincentNdegwa\EloquentTypegen\Support\Metadata\EnumMetadata;

class TypeResolution
{
    public function __construct(
        public string $type,
        public ?EnumMetadata $enum = null,
    ) {}
}
