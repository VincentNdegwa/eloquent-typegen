<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Scanners;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use VincentNdegwa\EloquentTypegen\Support\Metadata\ModelMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\ResourceFieldMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\ResourceMetadata;

class ResourceScanner
{
    private readonly Filesystem $filesystem;

    private readonly ParserFactory $parserFactory;

    /** @var array<string, ResourceMetadata> */
    private array $resources = [];

    /**
     * Model metadata indexed by short class name (e.g. 'User').
     * Used to resolve $this->property types.
     *
     * @var array<string, ModelMetadata>
     */
    private array $modelIndex = [];

    public function __construct(
        private readonly ?string $customScanPath = null,
    ) {
        $this->filesystem = new Filesystem;
        $this->parserFactory = new ParserFactory;
    }

    /**
     * Provide model metadata so the scanner can resolve $this->property types.
     *
     * @param  ModelMetadata[]  $models
     */
    public function withModels(array $models): static
    {
        foreach ($models as $model) {
            $this->modelIndex[$model->interfaceName] = $model;
        }

        return $this;
    }

    /**
     * @return ResourceMetadata[]
     */
    public function scan(): array
    {
        $resourcePaths = (array) config('typegen.resource_paths', ['Http/Resources']);

        foreach ($resourcePaths as $path) {
            $dir = $this->customScanPath ?? app_path($path);

            if (! $this->filesystem->isDirectory($dir)) {
                continue;
            }

            foreach ($this->filesystem->allFiles($dir) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $this->processResourceFile($file->getPathname());
            }
        }

        return array_values($this->resources);
    }

    private function processResourceFile(string $filePath): void
    {
        $code = $this->filesystem->get($filePath);

        try {
            $parser = $this->parserFactory->createForNewestSupportedVersion();
            $ast = $parser->parse($code);

            if ($ast === null) {
                return;
            }

            $traverser = new NodeTraverser;
            $visitor = new class extends NodeVisitorAbstract
            {
                public ?Class_ $resourceClass = null;

                public ?string $namespace = null;

                public ?ClassMethod $toArrayMethod = null;

                public function enterNode(Node $node): null
                {
                    if ($node instanceof Node\Stmt\Namespace_) {
                        $this->namespace = $node->name?->toString();
                    }

                    if ($node instanceof Class_ && $this->isResourceClass($node)) {
                        $this->resourceClass = $node;
                    }

                    if (
                        $node instanceof ClassMethod
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'toArray'
                    ) {
                        $this->toArrayMethod = $node;
                    }

                    return null;
                }

                private function isResourceClass(Class_ $node): bool
                {
                    if ($node->extends === null) {
                        return false;
                    }

                    // Use getLast() to match only the final class name segment,
                    // avoiding false matches on BaseResource, ApiResource, etc.
                    $last = $node->extends->getLast();

                    return $last === 'JsonResource' || $last === 'Resource';
                }
            };

            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            if ($visitor->resourceClass === null || $visitor->toArrayMethod === null) {
                return;
            }

            $shortName = $visitor->resourceClass->name instanceof Node\Identifier
                ? $visitor->resourceClass->name->toString()
                : (string) $visitor->resourceClass->name;

            $fullClassName = $visitor->namespace
                ? $visitor->namespace.'\\'.$shortName
                : $shortName;

            // UserResource → UserResource (keep Resource suffix to avoid name collisions)
            $interfaceName = $shortName;
            $fileName = Str::kebab($shortName).'.ts';
            $relatedModel = Str::replaceLast('Resource', '', $shortName);

            $metadata = new ResourceMetadata(
                className: $fullClassName,
                interfaceName: $interfaceName,
                fileName: $fileName,
                relatedModel: $relatedModel,
            );

            $metadata->fields = $this->parseToArrayMethod(
                $visitor->toArrayMethod,
                $relatedModel,
            );

            $this->resources[$fullClassName] = $metadata;
        } catch (Error) {
            // Skip unparseable files silently
        }
    }

    /**
     * @return ResourceFieldMetadata[]
     */
    private function parseToArrayMethod(ClassMethod $method, string $relatedModel): array
    {
        if ($method->stmts === null) {
            return [];
        }

        foreach ($method->stmts as $stmt) {
            if (! ($stmt instanceof Return_)) {
                continue;
            }

            if ($stmt->expr instanceof Array_) {
                return $this->parseArrayExpr($stmt->expr, $relatedModel);
            }

            // return array_merge(parent::toArray($request), [...])
            if ($stmt->expr instanceof Expr\FuncCall) {
                return $this->parseFuncCallExpr($stmt->expr, $relatedModel);
            }
        }

        return [];
    }

    /**
     * @return ResourceFieldMetadata[]
     */
    private function parseArrayExpr(Array_ $array, string $relatedModel): array
    {
        $fields = [];

        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            // Spread item: ...$this->mergeWhen($cond, [...])
            if ($item->key === null && $item->unpack) {
                foreach ($this->resolveMergeExpr($item->value, $relatedModel) as $mergedField) {
                    $fields[] = $mergedField;
                }

                continue;
            }

            if ($item->key === null) {
                continue;
            }

            $key = $this->extractString($item->key);
            if ($key === null) {
                continue;
            }

            $info = $this->analyzeValue($item->value, $relatedModel);

            $fields[] = new ResourceFieldMetadata(
                name: $key,
                type: $info['type'],
                optional: $info['optional'],
                conditional: $info['conditional'],
                nullable: $info['nullable'],
                condition: $info['condition'],
                nestedResource: $info['nestedResource'],
            );
        }

        return $fields;
    }

    /**
     * Handle return array_merge(parent::toArray($request), [...])
     *
     * @return ResourceFieldMetadata[]
     */
    private function parseFuncCallExpr(Expr\FuncCall $call, string $relatedModel): array
    {
        if (! $call->name instanceof Name || $call->name->toString() !== 'array_merge') {
            return [];
        }

        $fields = [];

        foreach ($call->getArgs() as $arg) {
            // Skip parent::toArray() — we can't trace parent class fields statically
            if ($arg->value instanceof StaticCall) {
                continue;
            }

            if ($arg->value instanceof Array_) {
                foreach ($this->parseArrayExpr($arg->value, $relatedModel) as $field) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Resolve ...$this->mergeWhen($condition, [...]) spread expressions.
     *
     * @return ResourceFieldMetadata[]
     */
    private function resolveMergeExpr(Expr $expr, string $relatedModel): array
    {
        if (! ($expr instanceof MethodCall)) {
            return [];
        }

        $method = $this->methodName($expr);

        if ($method !== 'mergeWhen' && $method !== 'merge') {
            return [];
        }

        $isConditional = $method === 'mergeWhen';
        $args = $expr->getArgs();
        $arrayArgIndex = $isConditional ? 1 : 0;
        $arrayArg = $args[$arrayArgIndex]->value ?? null;

        if (! ($arrayArg instanceof Array_)) {
            return [];
        }

        $fields = [];

        foreach ($this->parseArrayExpr($arrayArg, $relatedModel) as $field) {
            $fields[] = new ResourceFieldMetadata(
                name: $field->name,
                type: $field->type,
                optional: $isConditional ? true : $field->optional,
                conditional: $isConditional,
                nullable: $field->nullable,
                condition: $isConditional ? 'mergeWhen()' : $field->condition,
                nestedResource: $field->nestedResource,
            );
        }

        return $fields;
    }

    /**
     * Analyse a value node and return type information.
     *
     * @return array{type: string, optional: bool, conditional: bool, nullable: bool, condition: ?string, nestedResource: ?string}
     */
    private function analyzeValue(Expr $value, string $relatedModel): array
    {
        $result = [
            'type' => 'unknown',
            'optional' => false,
            'conditional' => false,
            'nullable' => false,
            'condition' => null,
            'nestedResource' => null,
        ];

        // $this->when($condition, $value)
        if ($value instanceof MethodCall && $this->methodName($value) === 'when') {
            $result['conditional'] = true;
            $result['optional'] = true;
            $result['condition'] = 'when()';
            $args = $value->getArgs();
            $inner = isset($args[1]) ? $this->unwrapClosure($args[1]->value) : null;
            if ($inner !== null) {
                $innerResult = $this->analyzeValue($inner, $relatedModel);
                $result['type'] = $innerResult['type'];
                $result['nullable'] = $innerResult['nullable'];
                $result['nestedResource'] = $innerResult['nestedResource'];
            }

            return $result;
        }

        // $this->whenLoaded('relation') or $this->whenLoaded('relation', fn() => ...)
        if ($value instanceof MethodCall && $this->methodName($value) === 'whenLoaded') {
            $result['conditional'] = true;
            $result['optional'] = true;
            $args = $value->getArgs();
            $relName = isset($args[0]) ? $this->extractString($args[0]->value) : null;
            $result['condition'] = 'whenLoaded'.($relName ? ": {$relName}" : '');
            $inner = isset($args[1]) ? $this->unwrapClosure($args[1]->value) : null;
            if ($inner !== null) {
                $innerResult = $this->analyzeValue($inner, $relatedModel);
                $result['type'] = $innerResult['type'];
                $result['nullable'] = $innerResult['nullable'];
                $result['nestedResource'] = $innerResult['nestedResource'];
            }

            return $result;
        }

        // $this->whenHas(), $this->whenNotNull(), $this->whenCounted()
        foreach (['whenHas', 'whenNotNull', 'whenCounted', 'whenAggregated'] as $cm) {
            if ($value instanceof MethodCall && $this->methodName($value) === $cm) {
                $result['conditional'] = true;
                $result['optional'] = true;
                $result['condition'] = "{$cm}()";
                $args = $value->getArgs();
                $inner = isset($args[1]) ? $this->unwrapClosure($args[1]->value) : null;
                if ($inner !== null) {
                    $innerResult = $this->analyzeValue($inner, $relatedModel);
                    $result['type'] = $innerResult['type'];
                    $result['nullable'] = $innerResult['nullable'];
                    $result['nestedResource'] = $innerResult['nestedResource'];
                }

                return $result;
            }
        }

        // new SomeResource($this->relation)
        if ($value instanceof New_) {
            $resourceClass = $this->extractClassName($value->class);
            if ($resourceClass !== null) {
                // Keep the Resource suffix to avoid name collisions
                $iface = class_basename($resourceClass);
                $result['type'] = $iface;
                $result['nestedResource'] = $resourceClass;
            }

            return $result;
        }

        // SomeResource::collection($this->relation)
        if ($value instanceof StaticCall && $this->methodName($value) === 'collection') {
            $resourceClass = $this->extractClassName($value->class);
            if ($resourceClass !== null) {
                // Keep the Resource suffix to avoid name collisions
                $iface = class_basename($resourceClass);
                $result['type'] = "{$iface}[]";
                $result['nestedResource'] = $resourceClass;
            }

            // If the collection argument is a whenLoaded call, mark as conditional
            $args = $value->getArgs();
            if (isset($args[0]) && $args[0]->value instanceof MethodCall) {
                $innerMethod = $this->methodName($args[0]->value);
                if ($innerMethod === 'whenLoaded') {
                    $result['conditional'] = true;
                    $result['optional'] = true;
                    $relArg = $args[0]->value->getArgs()[0] ?? null;
                    $relName = $relArg ? $this->extractString($relArg->value) : null;
                    $result['condition'] = 'whenLoaded'.($relName ? ": {$relName}" : '');
                }
            }

            return $result;
        }

        // $this->property — look up type from model metadata
        if ($value instanceof Expr\PropertyFetch) {
            $propName = $value->name instanceof Node\Identifier
                ? $value->name->toString()
                : null;
            if ($propName !== null) {
                $result['type'] = $this->resolveModelFieldType($relatedModel, $propName);
                // Check if the field is nullable in the model
                $model = $this->modelIndex[$relatedModel] ?? null;
                if ($model !== null) {
                    foreach ($model->fields as $field) {
                        if ($field->name === $propName && $field->nullable) {
                            $result['nullable'] = true;
                            break;
                        }
                    }
                }
            }

            return $result;
        }

        // $this->date?->format('Y-m-d') — nullsafe method call → nullable string
        if ($value instanceof Expr\NullsafeMethodCall) {
            $result['nullable'] = true;
            $result['type'] = 'string';

            return $result;
        }

        // $this->relation? — nullsafe property access
        if ($value instanceof Expr\NullsafePropertyFetch) {
            $result['nullable'] = true;

            return $result;
        }

        // $this->method() — detect ->format() etc.
        if ($value instanceof MethodCall) {
            $name = $this->methodName($value);
            if ($name !== null && str_ends_with($name, 'format')) {
                $result['type'] = 'string';
            }

            return $result;
        }

        // Plain PHP function calls
        if ($value instanceof Expr\FuncCall && $value->name instanceof Name) {
            $funcName = $value->name->toString();
            $result['type'] = match (true) {
                in_array($funcName, ['strtoupper', 'strtolower', 'ucfirst', 'ucwords',
                    'trim', 'ltrim', 'rtrim', 'route', 'url', 'asset', 'storage_url',
                    'implode', 'join', 'sprintf', 'number_format'], true) => 'string',
                in_array($funcName, ['count', 'strlen', 'intval', 'abs',
                    'round', 'floor', 'ceil'], true) => 'number',
                in_array($funcName, ['boolval'], true) => 'boolean',
                default => 'unknown',
            };

            return $result;
        }

        // Scalar literals
        if ($value instanceof String_) {
            $result['type'] = 'string';

            return $result;
        }

        if ($value instanceof Node\Scalar\LNumber || $value instanceof Node\Scalar\DNumber) {
            $result['type'] = 'number';

            return $result;
        }

        if ($value instanceof Expr\ConstFetch) {
            $constName = $value->name->toString();
            $result['type'] = match ($constName) {
                'true', 'false' => 'boolean',
                'null' => 'unknown',
                default => 'unknown',
            };
            if ($constName === 'null') {
                $result['nullable'] = true;
            }

            return $result;
        }

        return $result;
    }

    /**
     * If the expression is an arrow function or closure, unwrap to its return expression.
     */
    private function unwrapClosure(Expr $expr): ?Expr
    {
        if ($expr instanceof Expr\ArrowFunction) {
            return $expr->expr;
        }

        if ($expr instanceof Closure) {
            foreach ($expr->stmts as $stmt) {
                if ($stmt instanceof Return_ && $stmt->expr !== null) {
                    return $stmt->expr;
                }
            }

            return null;
        }

        return $expr;
    }

    /**
     * Resolve $this->propertyName to its TypeScript type via model metadata.
     */
    private function resolveModelFieldType(string $relatedModel, string $propertyName): string
    {
        $model = $this->modelIndex[$relatedModel] ?? null;
        if ($model === null) {
            return 'unknown';
        }

        foreach ($model->fields as $field) {
            if ($field->name === $propertyName) {
                return $field->type;
            }
        }

        return 'unknown';
    }

    private function methodName(MethodCall|StaticCall $expr): ?string
    {
        return $expr->name instanceof Node\Identifier
            ? $expr->name->toString()
            : null;
    }

    private function extractString(Expr $expr): ?string
    {
        return $expr instanceof String_ ? $expr->value : null;
    }

    private function extractClassName(Name|Expr|Class_ $node): ?string
    {
        if ($node instanceof Name) {
            return $node->toString();
        }

        if ($node instanceof Class_) {
            return $node->name instanceof Node\Identifier
                ? $node->name->toString()
                : null;
        }

        return null;
    }
}
