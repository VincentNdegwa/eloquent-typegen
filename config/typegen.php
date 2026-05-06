<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where to find your Eloquent models and where to save the
    | generated TypeScript files. You can also customize date representation,
    | exclude specific models, and override field types.
    |
    */

    'model_paths' => ['Models'],
    'output_path' => 'resources/js/eloquent-types',
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

    /*
    |--------------------------------------------------------------------------
    | Migration Type Mapping
    |--------------------------------------------------------------------------
    |
    | Map Laravel migration column types to TypeScript types.
    | These are used when inferring types from migration files.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Zod Schema Generation
    |--------------------------------------------------------------------------
    |
    | Generate Zod validation schemas alongside your TypeScript types.
    | Zod schemas can be used for runtime validation in your application.
    | You can configure a separate output path and enable a barrel file.
    |
    */

    'generate_zod' => false,
    'zod_output_path' => null,
    'generate_zod_index' => true,

    /*
    |--------------------------------------------------------------------------
    | API Resource Generation
    |--------------------------------------------------------------------------
    |
    | Generate TypeScript types from Laravel API Resources. You can choose
    | to generate types based on model fields, the resource's toArray method,
    | or both. Configure the paths to scan for resource classes.
    |
    */

    'resource_paths' => ['Http/Resources'],
    'generate_resources' => false,
    'resource_source' => 'model',

    /*
    |--------------------------------------------------------------------------
    | Form Request Generation
    |--------------------------------------------------------------------------
    |
    | Generate TypeScript types from Laravel Form Request validation rules.
    | Configure the paths to scan for request classes and enable generation.
    |
    */

    'request_paths' => ['Http/Requests'],
    'generate_requests' => false,

    /*
    |--------------------------------------------------------------------------
    | Root Index Barrel
    |--------------------------------------------------------------------------
    |
    | Generate a root barrel file that re-exports all types from subdirectories.
    | You can customize the filename or disable the barrel file entirely.
    |
    */

    'root_index_path' => 'types.ts',
];
