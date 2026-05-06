<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Metadata;

class FormRequestFieldMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        /**
         * True when the field itself may be absent from the request payload.
         * Caused by: sometimes, required_if, required_unless, required_with, etc.
         * Produces: field?: Type
         */
        public readonly bool $optional,
        /**
         * True when the field is present but its value can be null.
         * Caused by: nullable rule.
         * Produces: Nullable<Type>
         */
        public readonly bool $nullable,
        /**
         * Human-readable note about conditional validation rules.
         * Rendered as a TypeScript comment on the field.
         */
        public readonly ?string $comment = null,
    ) {}
}
