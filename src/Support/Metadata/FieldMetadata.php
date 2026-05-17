<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Metadata;

class FieldMetadata
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable = false,
        public bool $readonly = false,
        public bool $optional = false,
        public ?int $min = null,
        public ?int $max = null,
        public bool $unsigned = false,
    ) {}
}
