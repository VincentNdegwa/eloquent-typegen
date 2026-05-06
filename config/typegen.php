<?php

declare(strict_types=1);

return [
    'model_paths' => ['Models'],
    'output_path' => 'resources/js/types/models',
    'generate_index' => true,
    'generate_helpers' => true,
    'date_type' => 'string',
    'excluded_models' => [],
    'custom_type_map' => [],
    'include_relationships' => true,
    'include_vendor_models' => true,
    'additional_models' => [],
    'read_migrations' => true,
    'infer_types_from_migrations' => true,
    'migration_type_map' => [
        // integers
        'id' => 'number',
        'tinyInteger' => 'number',
        'smallInteger' => 'number',
        'mediumInteger' => 'number',
        'integer' => 'number',
        'bigInteger' => 'number',
        'unsignedTinyInteger' => 'number',
        'unsignedSmallInteger' => 'number',
        'unsignedMediumInteger' => 'number',
        'unsignedInteger' => 'number',
        'unsignedBigInteger' => 'number',
        'increments' => 'number',
        'tinyIncrements' => 'number',
        'smallIncrements' => 'number',
        'mediumIncrements' => 'number',
        'bigIncrements' => 'number',
        'foreignId' => 'number',
        'foreignUuid' => 'string',
        'foreignUlid' => 'string',
        // floats / decimals
        'float' => 'number',
        'double' => 'number',
        'decimal' => 'number',
        'unsignedFloat' => 'number',
        'unsignedDouble' => 'number',
        'unsignedDecimal' => 'number',
        // booleans
        'boolean' => 'boolean',
        // strings
        'char' => 'string',
        'string' => 'string',
        'tinyText' => 'string',
        'text' => 'string',
        'mediumText' => 'string',
        'longText' => 'string',
        'uuid' => 'string',
        'ulid' => 'string',
        'ipAddress' => 'string',
        'macAddress' => 'string',
        'enum' => 'string',
        'set' => 'string',
        // dates
        'date' => 'date',
        'dateTime' => 'date',
        'dateTimeTz' => 'date',
        'time' => 'string',
        'timeTz' => 'string',
        'timestamp' => 'date',
        'timestampTz' => 'date',
        'year' => 'number',
        // json
        'json' => 'json',
        'jsonb' => 'json',
        // binary
        'binary' => 'string',
        'geometry' => 'string',
    ],
    // v1.1 - Zod schema generation
    'generate_zod' => false,
    'zod_output_path' => null, // defaults to same as output_path
    // v2 - API Resource scanning
    'resource_paths' => ['Http/Resources'],
    'generate_resources' => false,
    'resource_source' => 'model', // 'model' | 'resource' | 'both'
    // v2.5 - Form Request scanning
    'request_paths' => ['Http/Requests'],
    'generate_requests' => false,
];
