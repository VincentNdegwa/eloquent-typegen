<?php

declare(strict_types=1);

namespace Based\EloquentTypegen\Support\Metadata;

class AccessorMetadata
{
    public function __construct(
        public string $name,
    ) {}
}
