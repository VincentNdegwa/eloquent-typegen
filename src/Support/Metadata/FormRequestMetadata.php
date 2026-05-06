<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Metadata;

class FormRequestMetadata
{
    public function __construct(
        public readonly string $className,
        public readonly string $interfaceName,
        public readonly string $fileName,
        /** @var array<FormRequestFieldMetadata> */
        public array $fields = [],
    ) {}
}
