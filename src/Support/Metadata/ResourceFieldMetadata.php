<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Metadata;

class ResourceFieldMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        /**
         * True when the field may be absent from the response.
         * Caused by: when(), whenLoaded(), whenHas(), whenNotNull(), mergeWhen()
         * Produces: field?: Type
         */
        public readonly bool $optional,
        /**
         * True when optionality comes from a Laravel conditional method.
         * Allows the generator to add an explanatory comment.
         */
        public readonly bool $conditional,
        /**
         * True when the field value can be null.
         * e.g. $this->deleted_at?->format('Y-m-d') → string | null
         * Produces: Nullable<Type>
         */
        public readonly bool $nullable = false,
        /**
         * Human-readable description of the condition (e.g. 'whenLoaded: posts').
         * Rendered as an inline TypeScript comment.
         */
        public readonly ?string $condition = null,
        /**
         * Short class name of the nested resource if this field is a resource type.
         * e.g. 'PostResource' — used to resolve the TypeScript interface name.
         */
        public readonly ?string $nestedResource = null,
    ) {}
}
