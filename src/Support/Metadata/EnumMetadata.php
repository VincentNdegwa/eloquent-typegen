<?php

declare(strict_types=1);

namespace Based\EloquentTypegen\Support\Metadata;

class EnumMetadata
{
    public function __construct(
        public string $name,
        public string $definition,
    ) {}
}
