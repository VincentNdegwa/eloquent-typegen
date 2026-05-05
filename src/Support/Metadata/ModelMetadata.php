<?php

declare(strict_types=1);

namespace Based\EloquentTypegen\Support\Metadata;

class ModelMetadata
{
    /** @var FieldMetadata[] */
    public array $fields = [];

    /** @var RelationMetadata[] */
    public array $relations = [];

    /** @var EnumMetadata[] */
    public array $enums = [];

    /** @var AccessorMetadata[] */
    public array $accessors = [];

    public function __construct(
        public string $className,
        public string $interfaceName,
        public string $fileName,
    ) {
    }
}
