<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Metadata;

class ResourceMetadata
{
    public function __construct(
        public readonly string $className,
        public readonly string $interfaceName,
        public readonly string $fileName,
        /** @var array<ResourceFieldMetadata> */
        public array $fields = [],
        /** @var array<EnumMetadata> */
        public array $enums = [],
        /**
         * The short model class name this resource wraps, if determinable.
         * e.g. 'User' for UserResource. Used to look up model field types
         * when resolving $this->property accesses.
         */
        public ?string $relatedModel = null,
    ) {}
}
