<?php

declare(strict_types=1);

namespace Based\EloquentTypegen\Support\Metadata;

class RelationMetadata
{
    public function __construct(
        public string $name,
        public string $type,
    ) {}
}
