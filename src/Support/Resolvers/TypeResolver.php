<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Resolvers;

use BackedEnum;
use UnitEnum;
use VincentNdegwa\EloquentTypegen\Support\Metadata\EnumMetadata;

class TypeResolver
{
    public function __construct(
        private readonly string $dateType,
        /** @var array<string, string> */
        private readonly array $customTypeMap = [],
    ) {}

    public function resolve(?string $castType): TypeResolution
    {
        if ($castType === null || $castType === '') {
            return new TypeResolution('unknown');
        }

        if (array_key_exists($castType, $this->customTypeMap)) {
            return new TypeResolution($this->customTypeMap[$castType]);
        }

        $normalized = $this->normalizeCast($castType);

        if (array_key_exists($normalized, $this->customTypeMap)) {
            return new TypeResolution($this->customTypeMap[$normalized]);
        }

        if (str_contains($normalized, ':')) {
            [$base, $param] = explode(':', $normalized, 2);

            if ($base === 'decimal') {
                return new TypeResolution('number');
            }

            if (str_ends_with($base, 'AsEnumCollection')) {
                return $this->resolveEnumCollection($param);
            }

            $normalized = $base;
        }

        $scalarMap = [
            'int' => 'number',
            'integer' => 'number',
            'bigInteger' => 'number',
            'float' => 'number',
            'double' => 'number',
            'decimal' => 'number',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'string' => 'string',
            'char' => 'string',
            'varchar' => 'string',
            'text' => 'string',
            'uuid' => 'string',
            'ulid' => 'string',
            'date' => $this->dateType,
            'datetime' => $this->dateType,
            'timestamp' => $this->dateType,
            'immutable_date' => $this->dateType,
            'immutable_datetime' => $this->dateType,
            'array' => 'Record<string, unknown>',
            'json' => 'Record<string, unknown>',
            'jsonb' => 'Record<string, unknown>',
            'object' => 'Record<string, unknown>',
            'collection' => 'unknown[]',
            // Migration-scanner internal tokens (not standard Laravel casts)
            'number' => 'number',
        ];

        if (array_key_exists($normalized, $scalarMap)) {
            return new TypeResolution($scalarMap[$normalized]);
        }

        if (str_ends_with($normalized, 'AsCollection') || str_ends_with($normalized, 'AsArrayObject')) {
            return new TypeResolution('unknown[]');
        }

        if (str_ends_with($normalized, 'AsStringable')) {
            return new TypeResolution('string');
        }

        if (class_exists($normalized)) {
            if (is_subclass_of($normalized, BackedEnum::class) || is_subclass_of($normalized, UnitEnum::class)) {
                return $this->resolveEnum($normalized);
            }

            if (method_exists($normalized, 'toTypeScript')) {
                try {
                    $result = $normalized::toTypeScript();
                    if (is_string($result) && $result !== '') {
                        return new TypeResolution($result);
                    }
                } catch (\Throwable $exception) {
                    // Fall through to unknown for unsafe custom cast resolution.
                }
            }
        }

        return new TypeResolution('unknown');
    }

    private function normalizeCast(string $castType): string
    {
        return ltrim($castType, '\\');
    }

    private function resolveEnum(string $enumClass): TypeResolution
    {
        $cases = $enumClass::cases();
        $values = [];

        foreach ($cases as $case) {
            if ($case instanceof BackedEnum) {
                $values[] = $this->formatLiteral($case->value);
            } else {
                $values[] = $this->formatLiteral($case->name);
            }
        }

        $definition = implode(' | ', $values);
        $name = class_basename($enumClass);

        return new TypeResolution($name, new EnumMetadata($name, $definition));
    }

    private function resolveEnumCollection(string $enumClass): TypeResolution
    {
        $enumClass = $this->normalizeCast($enumClass);

        if (class_exists($enumClass) && (is_subclass_of($enumClass, BackedEnum::class) || is_subclass_of($enumClass, UnitEnum::class))) {
            $resolution = $this->resolveEnum($enumClass);

            return new TypeResolution($resolution->type.'[]', $resolution->enum);
        }

        return new TypeResolution('unknown[]');
    }

    private function formatLiteral(int|string $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        $escaped = addslashes($value);

        return "'{$escaped}'";
    }
}
