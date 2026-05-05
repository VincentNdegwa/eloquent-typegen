<?php

declare(strict_types=1);

namespace Based\EloquentTypegen\Support\Metadata;

class FieldMetadata
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable = false,
        public bool $readonly = false,
        public bool $optional = false,
    ) {
    }
}
