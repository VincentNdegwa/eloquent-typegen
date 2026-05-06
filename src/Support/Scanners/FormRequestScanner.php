<?php

declare(strict_types=1);

namespace VincentNdegwa\EloquentTypegen\Support\Scanners;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use VincentNdegwa\EloquentTypegen\Support\Metadata\FormRequestFieldMetadata;
use VincentNdegwa\EloquentTypegen\Support\Metadata\FormRequestMetadata;

class FormRequestScanner
{
    private readonly Filesystem $filesystem;

    private readonly ParserFactory $parserFactory;

    /** @var array<string, FormRequestMetadata> */
    private array $requests = [];

    public function __construct(
        private readonly ?string $customScanPath = null,
    ) {
        $this->filesystem = new Filesystem;
        $this->parserFactory = new ParserFactory;
    }

    /**
     * @return FormRequestMetadata[]
     */
    public function scan(): array
    {
        $requestPaths = (array) config('typegen.request_paths', ['Http/Requests']);

        foreach ($requestPaths as $path) {
            $dir = $this->customScanPath ?? app_path($path);

            if (! $this->filesystem->isDirectory($dir)) {
                continue;
            }

            foreach ($this->filesystem->allFiles($dir) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $this->processRequestFile($file->getPathname());
            }
        }

        return array_values($this->requests);
    }

    private function processRequestFile(string $filePath): void
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
                public ?Class_ $requestClass = null;

                public ?string $namespace = null;

                public ?ClassMethod $rulesMethod = null;

                public function enterNode(Node $node): null
                {
                    if ($node instanceof Namespace_) {
                        $this->namespace = $node->name?->toString();
                    }

                    if ($node instanceof Class_ && $this->isFormRequestClass($node)) {
                        $this->requestClass = $node;
                    }

                    if (
                        $node instanceof ClassMethod
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'rules'
                    ) {
                        $this->rulesMethod = $node;
                    }

                    return null;
                }

                private function isFormRequestClass(Class_ $node): bool
                {
                    if ($node->extends === null) {
                        return false;
                    }

                    // Only match classes that explicitly extend FormRequest.
                    // Avoid matching every class with "Request" in the parent name
                    // (e.g. ApiRequest, BaseRequest, IlluminateRequest).
                    $extendsName = $node->extends->getLast();

                    return $extendsName === 'FormRequest';
                }
            };

            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            if ($visitor->requestClass === null || $visitor->rulesMethod === null) {
                return;
            }

            $className = $visitor->requestClass->name instanceof Node\Identifier
                ? $visitor->requestClass->name->toString()
                : (string) $visitor->requestClass->name;

            $fullClassName = $visitor->namespace
                ? $visitor->namespace.'\\'.$className
                : $className;

            $rawFields = $this->parseRulesMethod($visitor->rulesMethod);

            // Cross-reference array wildcard fields (e.g. tags.* refines tags to string[])
            $fields = $this->resolveWildcardFields($rawFields);

            // Strip the Request suffix for the interface name: StoreUserRequest → StoreUser
            $interfaceName = Str::replaceLast('Request', '', $className);
            $fileName = Str::kebab($interfaceName).'.ts';

            $metadata = new FormRequestMetadata($fullClassName, $interfaceName, $fileName);
            $metadata->fields = $fields;

            $this->requests[$fullClassName] = $metadata;
        } catch (Error) {
            // Skip files that can't be parsed — log if debug mode enabled
        }
    }

    /**
     * @return FormRequestFieldMetadata[]
     */
    private function parseRulesMethod(ClassMethod $method): array
    {
        $fields = [];

        if ($method->stmts === null) {
            return $fields;
        }

        foreach ($method->stmts as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof Array_) {
                $fields = $this->parseArrayExpr($stmt->expr);
            }
        }

        return $fields;
    }

    /**
     * @return FormRequestFieldMetadata[]
     */
    private function parseArrayExpr(Array_ $array): array
    {
        $fields = [];

        foreach ($array->items as $item) {
            if ($item === null || $item->key === null) {
                continue;
            }

            $fieldName = $this->extractStringValue($item->key);
            if ($fieldName === null) {
                continue;
            }

            // Rules can be a pipe-separated string OR an array of strings/Rule objects
            $rules = $this->extractRules($item->value);
            if (empty($rules)) {
                continue;
            }

            $fieldInfo = $this->analyzeRules($fieldName, $rules);

            $fields[] = new FormRequestFieldMetadata(
                name: $fieldName,
                type: $fieldInfo['type'],
                optional: $fieldInfo['optional'],
                nullable: $fieldInfo['nullable'],
                comment: $fieldInfo['comment'],
            );

            // confirmed rule: password|confirmed → also add password_confirmation field
            if (in_array('confirmed', $rules, true)) {
                $fields[] = new FormRequestFieldMetadata(
                    name: $fieldName.'_confirmation',
                    type: 'string',
                    optional: false,
                    nullable: false,
                    comment: "confirmation for {$fieldName}",
                );
            }
        }

        return $fields;
    }

    /**
     * Extract rules from either a pipe-separated string or an array node.
     * Handles: 'required|string', ['required', 'string'], ['required', Rule::in([...])]
     *
     * @return string[]
     */
    private function extractRules(Node $node): array
    {
        // String rules: 'required|string|max:255'
        if ($node instanceof String_) {
            return array_map('trim', explode('|', $node->value));
        }

        // Array rules: ['required', 'string', Rule::in(['a', 'b'])]
        if ($node instanceof Array_) {
            $rules = [];

            foreach ($node->items as $item) {
                if ($item === null) {
                    continue;
                }

                // Plain string rule
                if ($item->value instanceof String_) {
                    $rules[] = trim($item->value->value);
                    continue;
                }

                // Static call: Rule::in([...]), Rule::enum(MyEnum::class)
                if ($item->value instanceof StaticCall) {
                    $resolved = $this->resolveStaticRuleCall($item->value);
                    if ($resolved !== null) {
                        $rules[] = $resolved;
                    }
                }
            }

            return $rules;
        }

        return [];
    }

    /**
     * Resolve Rule::in([...]) and Rule::enum(MyEnum::class) static calls
     * into synthetic rule strings we can recognise in analyzeRules().
     */
    private function resolveStaticRuleCall(StaticCall $call): ?string
    {
        if (! $call->name instanceof Node\Identifier) {
            return null;
        }

        $method = $call->name->toString();

        // Rule::in(['admin', 'editor', 'viewer']) → 'in:admin,editor,viewer'
        if ($method === 'in' && ! empty($call->args)) {
            $arg = $call->args[0]->value ?? null;
            if ($arg instanceof Array_) {
                $values = [];
                foreach ($arg->items as $item) {
                    if ($item && $item->value instanceof String_) {
                        $values[] = $item->value->value;
                    }
                }
                if (! empty($values)) {
                    return 'in:'.implode(',', $values);
                }
            }
        }

        // Rule::enum(UserRole::class) → 'enum:UserRole'
        // We can't resolve the enum values without loading the class, so we
        // emit a synthetic 'enum:ClassName' token and resolve it in analyzeRules
        if ($method === 'enum' && ! empty($call->args)) {
            $arg = $call->args[0]->value ?? null;
            if ($arg instanceof Node\Expr\ClassConstFetch) {
                $enumClass = $arg->class instanceof Node\Name
                    ? $arg->class->getLast()
                    : null;
                if ($enumClass !== null) {
                    return 'enum:'.$enumClass;
                }
            }
        }

        return null;
    }

    private function extractStringValue(Node $node): ?string
    {
        return $node instanceof String_ ? $node->value : null;
    }

    /**
     * Analyse an array of rule strings and return resolved type info.
     *
     * @param  string[]  $rules
     * @return array{type: string, optional: bool, nullable: bool, comment: ?string}
     */
    private function analyzeRules(string $fieldName, array $rules): array
    {
        $type = 'unknown';
        $optional = true;   // Default to optional — fields that aren't marked required are optional
        $nullable = false;
        $comment = null;
        $conditionalRules = [];

        foreach ($rules as $rule) {
            $rule = trim($rule);
            [$ruleName, $ruleParam] = array_pad(explode(':', $rule, 2), 2, null);

            switch ($ruleName) {
                case 'required':
                    // Only make non-optional if not already conditional
                    if (empty($conditionalRules)) {
                        $optional = false;
                    }
                    break;

                case 'nullable':
                    $nullable = true;
                    // nullable does NOT make the field optional — it makes the VALUE nullable
                    // A field can be required|nullable (must be present, but can be null)
                    break;

                case 'sometimes':
                    // Field is only validated if it appears in the request
                    $optional = true;
                    $conditionalRules[] = 'sometimes';
                    break;

                case 'required_if':
                case 'required_unless':
                case 'required_with':
                case 'required_without':
                case 'required_with_all':
                case 'required_without_all':
                    $optional = true; // Conditionally required = optional from TS perspective
                    $conditionalRules[] = $ruleName.($ruleParam ? ':'.$ruleParam : '');
                    break;

                // String types
                case 'string':
                case 'email':
                case 'url':
                case 'password':
                case 'alpha':
                case 'alpha_dash':
                case 'alpha_num':
                case 'ip':
                case 'ipv4':
                case 'ipv6':
                case 'mac_address':
                case 'timezone':
                case 'uuid':
                case 'ulid':
                    $type = 'string';
                    break;

                // Number types
                case 'integer':
                case 'int':
                case 'numeric':
                case 'digits':
                case 'digits_between':
                    $type = 'number';
                    break;

                // Boolean
                case 'boolean':
                case 'bool':
                    $type = 'boolean';
                    break;

                // Array — type refined later if wildcard rule exists
                case 'array':
                    if ($type === 'unknown') {
                        $type = 'unknown[]';
                    }
                    break;

                // Date
                case 'date':
                case 'date_format':
                case 'date_equals':
                case 'before':
                case 'after':
                case 'before_or_equal':
                case 'after_or_equal':
                    $dateType = config('typegen.date_type', 'string');
                    $type = $dateType === 'Date' ? 'Date' : 'string';
                    break;

                // Enum-like: in:a,b,c
                case 'in':
                    if ($ruleParam !== null) {
                        $values = explode(',', $ruleParam);
                        $type = implode(' | ', array_map(
                            fn (string $v) => "'".trim($v)."'",
                            $values
                        ));
                    }
                    break;

                // Rule::enum(MyEnum::class) resolved as 'enum:ClassName'
                case 'enum':
                    if ($ruleParam !== null && class_exists($ruleParam)) {
                        // Class is loaded — extract the actual values
                        $cases = $ruleParam::cases();
                        $values = array_map(function ($case) {
                            return property_exists($case, 'value')
                                ? (is_string($case->value) ? "'{$case->value}'" : (string) $case->value)
                                : "'{$case->name}'";
                        }, $cases);
                        $type = implode(' | ', $values);
                    } elseif ($ruleParam !== null) {
                        // Can't load the class statically — use the name as a hint
                        $type = 'string'; // safe fallback
                        $comment = "enum: {$ruleParam}";
                    }
                    break;

                // File types — type as string (base64 or filename)
                case 'file':
                case 'image':
                case 'mimes':
                case 'mimetypes':
                    $type = 'string'; // In practice frontend sends File object or base64
                    $comment = "file upload: {$ruleName}";
                    break;

                // Rules that add a comment but don't change the type
                case 'regex':
                case 'not_regex':
                case 'dimensions':
                    if ($type === 'unknown') {
                        $type = 'string';
                    }
                    $comment = "validation: {$ruleName}";
                    break;

                // Ignore: structural/meta rules that don't affect the TS type
                case 'confirmed':
                case 'same':
                case 'different':
                case 'unique':
                case 'exists':
                case 'bail':
                case 'filled':
                case 'present':
                case 'prohibited':
                case 'min':
                case 'max':
                case 'between':
                case 'size':
                case 'lt':
                case 'lte':
                case 'gt':
                case 'gte':
                case 'in_array':
                case 'distinct':
                case 'not_in':
                    break;
            }
        }

        if (! empty($conditionalRules)) {
            $comment = 'conditional: '.implode(', ', $conditionalRules);
        }

        return [
            'type' => $type,
            'optional' => $optional,
            'nullable' => $nullable,
            'comment' => $comment,
        ];
    }

    /**
     * Cross-reference wildcard fields with their parent.
     *
     * If we have:
     *   tags => unknown[]
     *   tags.* => string
     *
     * We should produce:
     *   tags => string[]
     *
     * And drop the tags.* field entirely (it's not a real TypeScript field).
     *
     * @param  FormRequestFieldMetadata[]  $fields
     * @return FormRequestFieldMetadata[]
     */
    private function resolveWildcardFields(array $fields): array
    {
        // Build a map of parent field names that have a wildcard child
        $wildcardTypes = [];
        foreach ($fields as $field) {
            if (str_contains($field->name, '.*')) {
                $parent = Str::before($field->name, '.*');
                $wildcardTypes[$parent] = $field->type;
            }
        }

        $resolved = [];
        foreach ($fields as $field) {
            // Drop wildcard fields — they're not real TS fields
            if (str_contains($field->name, '.*')) {
                continue;
            }

            // If this field has a wildcard child, refine the type
            if (isset($wildcardTypes[$field->name])) {
                $itemType = $wildcardTypes[$field->name];
                $resolved[] = new FormRequestFieldMetadata(
                    name: $field->name,
                    type: "{$itemType}[]",
                    optional: $field->optional,
                    nullable: $field->nullable,
                    comment: $field->comment,
                );
                continue;
            }

            $resolved[] = $field;
        }

        return $resolved;
    }
}
