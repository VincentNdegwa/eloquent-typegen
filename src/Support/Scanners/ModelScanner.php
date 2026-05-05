<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Scanners;

use VincentNdegwa\EloquentTypegen\Support\Helpers\NameHelper;
use VincentNdegwa\EloquentTypegen\Support\Metadata\AccessorMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\EnumMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\FieldMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\ModelMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\RelationMetadata;
use VincentNdegwa\EloquentTypegen\Support\Resolvers\NullabilityResolver;
use VincentNdegwa\EloquentTypegen\Support\Resolvers\TypeResolver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;

class ModelScanner
{
    private readonly Filesystem $filesystem;

    private readonly TypeResolver $typeResolver;

    private readonly NullabilityResolver $nullabilityResolver;

    private readonly bool $includeVendorModels;

    /** @var string[] */
    private readonly array $additionalModels;

    /** @var array<string, true> */
    private array $relatedClasses = [];

    public function __construct()
    {
        $this->filesystem = new Filesystem;
        $this->typeResolver = new TypeResolver(
            (string) config('typegen.date_type', 'string'),
            (array) config('typegen.custom_type_map', []),
        );
        $this->nullabilityResolver = new NullabilityResolver(
            (bool) config('typegen.read_migrations', true)
        );
        $this->nullabilityResolver->bootstrap();
        $this->includeVendorModels = (bool) config('typegen.include_vendor_models', true);
        $this->additionalModels = (array) config('typegen.additional_models', []);
    }

    /**
     * @param  string[]  $onlyModels
     * @return ModelMetadata[]
     */
    public function scan(array $onlyModels = []): array
    {
        $modelPaths = (array) config('typegen.model_paths', ['Models']);
        $excluded = (array) config('typegen.excluded_models', []);
        $namespace = app()->getNamespace();

        $results = [];
        $seen = [];

        foreach ($modelPaths as $path) {
            $dir = app_path($path);
            if (! $this->filesystem->isDirectory($dir)) {
                continue;
            }

            foreach ($this->filesystem->allFiles($dir) as $file) {
                $class = $this->classFromFile($file->getPathname(), $namespace);
                if ($class === null || in_array($class, $excluded, true)) {
                    continue;
                }

                $this->addModelByClass($class, $file->getPathname(), $onlyModels, $excluded, $results, $seen, false);
            }
        }

        $this->includeAdditionalModels($onlyModels, $excluded, $results, $seen);

        return $results;
    }

    /**
     * @param  string[]  $onlyModels
     * @param  string[]  $excluded
     * @param  ModelMetadata[]  $results
     * @param  array<string, true>  $seen
     */
    private function addModelByClass(
        string $class,
        ?string $filePath,
        array $onlyModels,
        array $excluded,
        array &$results,
        array &$seen,
        bool $skipFilter
    ): void {
        if (in_array($class, $excluded, true)) {
            return;
        }

        if (! $skipFilter && ! $this->matchesModelFilter($class, $onlyModels)) {
            return;
        }

        if (isset($seen[$class])) {
            return;
        }

        if (! class_exists($class) && $filePath) {
            require_once $filePath;
        }

        if (! class_exists($class)) {
            return;
        }

        $reflection = new ReflectionClass($class);
        if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
            return;
        }

        $model = $reflection->newInstance();
        $metadata = $this->buildMetadata($model, $class);
        $results[] = $metadata;
        $seen[$class] = true;
    }

    /**
     * @param  string[]  $onlyModels
     * @param  string[]  $excluded
     * @param  ModelMetadata[]  $results
     * @param  array<string, true>  $seen
     */
    private function includeAdditionalModels(array $onlyModels, array $excluded, array &$results, array &$seen): void
    {
        $queue = [];
        $processed = [];

        foreach ($this->additionalModels as $class) {
            $class = trim((string) $class);
            if ($class !== '') {
                $queue[] = $class;
            }
        }

        if ($this->includeVendorModels) {
            foreach (array_keys($this->relatedClasses) as $class) {
                $queue[] = $class;
            }
        }

        while (! empty($queue)) {
            $class = array_shift($queue);
            if ($class === null || isset($processed[$class])) {
                continue;
            }

            $processed[$class] = true;
            $this->addModelByClass($class, null, $onlyModels, $excluded, $results, $seen, true);

            if (! $this->includeVendorModels) {
                continue;
            }

            foreach (array_keys($this->relatedClasses) as $relatedClass) {
                if (! isset($processed[$relatedClass])) {
                    $queue[] = $relatedClass;
                }
            }
        }
    }

    private function buildMetadata(Model $model, string $class): ModelMetadata
    {
        $interfaceName = NameHelper::modelToInterface($class);
        $fileName = NameHelper::modelToFileName($class);
        $metadata = new ModelMetadata($class, $interfaceName, $fileName);

        $casts = $model->getCasts();
        $fillable = $model->getFillable();
        $hidden = $model->getHidden();
        $dates = $model->getDates();

        $fieldNames = array_unique(array_merge(array_keys($casts), $fillable, $dates));

        foreach ($fieldNames as $field) {
            if (in_array($field, $hidden, true)) {
                continue;
            }

            $castType = $casts[$field] ?? null;
            if ($castType === null && in_array($field, $dates, true)) {
                $castType = 'datetime';
            }

            $resolution = $this->typeResolver->resolve(is_string($castType) ? $castType : null);
            if ($resolution->enum !== null) {
                $this->addEnum($metadata, $resolution->enum);
            }

            $nullable = $this->nullabilityResolver->isNullable($model->getTable(), (string) $field);
            $metadata->fields[] = new FieldMetadata(
                (string) $field,
                $resolution->type,
                $nullable,
                false,
                $nullable,
            );
        }

        $this->addAccessors($metadata, $model, $hidden);
        $this->addRelations($metadata, $model);

        return $metadata;
    }

    /** @param  array<string>  $hidden */
    private function addAccessors(ModelMetadata $metadata, Model $model, array $hidden): void
    {
        $reflection = new ReflectionClass($model);
        $fieldNames = array_map(static fn (FieldMetadata $field) => $field->name, $metadata->fields);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfParameters() > 0 || $method->isStatic()) {
                continue;
            }

            $returnType = $method->getReturnType();
            if ($returnType === null || ! $returnType instanceof \ReflectionNamedType || $returnType->getName() !== Attribute::class) {
                continue;
            }

            $fieldName = NameHelper::accessorToField($method->getName());
            if (in_array($fieldName, $hidden, true)) {
                continue;
            }

            if (in_array($fieldName, $fieldNames, true)) {
                continue;
            }

            $metadata->accessors[] = new AccessorMetadata($fieldName);
        }
    }

    private function addRelations(ModelMetadata $metadata, Model $model): void
    {
        if (! (bool) config('typegen.include_relationships', true)) {
            return;
        }

        $reflection = new ReflectionClass($model);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfParameters() > 0 || $method->isStatic()) {
                continue;
            }

            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            try {
                $relation = $method->invoke($model);
            } catch (\Throwable $exception) {
                continue;
            }

            if (! $relation instanceof Relation) {
                continue;
            }

            $fieldName = $method->getName();

            if ($relation instanceof MorphTo) {
                $metadata->relations[] = new RelationMetadata($fieldName, 'unknown');

                continue;
            }

            $related = $relation->getRelated();
            $this->relatedClasses[$related::class] = true;
            $relatedType = NameHelper::modelToInterface($related::class);

            if ($relation instanceof HasOne || $relation instanceof BelongsTo || $relation instanceof MorphOne) {
                $metadata->relations[] = new RelationMetadata($fieldName, $relatedType);

                continue;
            }

            if (
                $relation instanceof HasMany ||
                $relation instanceof BelongsToMany ||
                $relation instanceof MorphMany ||
                $relation instanceof HasManyThrough
            ) {
                $metadata->relations[] = new RelationMetadata($fieldName, $relatedType.'[]');
            }
        }
    }

    private function addEnum(ModelMetadata $metadata, EnumMetadata $enum): void
    {
        foreach ($metadata->enums as $existing) {
            if ($existing->name === $enum->name) {
                return;
            }
        }

        $metadata->enums[] = $enum;
    }

    private function classFromFile(string $filePath, string $namespace): ?string
    {
        $appPath = app_path();
        if (! Str::startsWith($filePath, $appPath)) {
            return null;
        }

        $relative = Str::after($filePath, $appPath.DIRECTORY_SEPARATOR);
        $relative = Str::replaceLast('.php', '', $relative);
        $relative = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

        return $namespace.$relative;
    }

    /**
     * @param  string[]  $onlyModels
     */
    private function matchesModelFilter(string $class, array $onlyModels): bool
    {
        if (empty($onlyModels)) {
            return true;
        }

        $base = class_basename($class);

        foreach ($onlyModels as $filter) {
            $filter = trim((string) $filter);
            if ($filter === '') {
                continue;
            }

            if ($filter === $class || $filter === $base) {
                return true;
            }
        }

        return false;
    }
}
