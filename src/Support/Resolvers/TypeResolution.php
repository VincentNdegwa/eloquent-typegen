<?php

declare(strict_types=1);

namespace Based\EloquentTypegen\Support\Resolvers;

use Based\EloquentTypegen\Support\Metadata\EnumMetadata;

class TypeResolution
{
    public function __construct(
        public string $type,
        public ?EnumMetadata $enum = null,
    ) {
    }
}
