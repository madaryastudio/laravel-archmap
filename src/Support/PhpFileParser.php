<?php

namespace Ardana\Archmap\Support;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\VariadicPlaceholder;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final class PhpFileParser
{
    /**
     * @return array{
     *   namespace: string|null,
     *   class: string|null,
     *   kind: string|null,
     *   extends: string|null,
     *   implements: list<string>,
     *   public_methods: int,
     *   constructor_dependencies: list<string>
     * }
     */
    public function parse(string $path): array
    {
        $source = @file_get_contents($path);
        if ($source === false) {
            return $this->emptyResult();
        }

        $ast = $this->parseAst($source);
        if ($ast !== null) {
            $parsed = $this->parseAstClassMeta($ast);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $source, $m) === 1) {
            $namespace = trim($m[1]);
        }
        $kind = null;
        $class = null;
        $extends = null;
        $implements = [];
        if (preg_match('/\b(class|interface|trait)\s+([A-Za-z_][A-Za-z0-9_]*)\s*(?:extends\s+([A-Za-z0-9_\\\\]+))?\s*(?:implements\s+([A-Za-z0-9_\\\\,\s]+))?/m', $source, $m) === 1) {
            $kind = $m[1];
            $class = $m[2];
            $extends = isset($m[3]) && $m[3] !== '' ? trim($m[3]) : null;
            if (!empty($m[4])) {
                $implements = array_values(array_filter(array_map('trim', explode(',', $m[4]))));
            }
        }

        $publicMethods = preg_match_all('/public\s+function\s+[A-Za-z_][A-Za-z0-9_]*\s*\(/', $source);
        $deps = [];
        if (preg_match('/function\s+__construct\s*\((.*?)\)/s', $source, $m) === 1) {
            $params = preg_split('/\s*,\s*/', trim($m[1])) ?: [];
            foreach ($params as $param) {
                if (preg_match('/^\s*([A-Za-z0-9_\\\\]+)\s+\$/', $param, $pm) === 1) {
                    $deps[] = trim($pm[1], '\\');
                }
            }
        }

        return [
            'namespace' => $namespace,
            'class' => $class,
            'kind' => $kind,
            'extends' => $extends,
            'implements' => $implements,
            'public_methods' => $publicMethods === false ? 0 : $publicMethods,
            'constructor_dependencies' => array_values(array_unique($deps)),
        ];
    }

    /**
     * @return list<array{type: string, related: string, method: string}>
     */
    public function findRelationships(string $path): array
    {
        $source = @file_get_contents($path);
        if ($source === false) {
            return [];
        }

        $ast = $this->parseAst($source);
        if ($ast !== null) {
            $relationships = $this->parseAstRelationships($ast);
            if ($relationships !== []) {
                return $relationships;
            }
        }

        $patterns = [
            'hasOne' => '/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s*{[\s\S]*?\$this->hasOne\(\s*([A-Za-z0-9_\\\\:]+)::class/s',
            'hasMany' => '/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s*{[\s\S]*?\$this->hasMany\(\s*([A-Za-z0-9_\\\\:]+)::class/s',
            'belongsTo' => '/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s*{[\s\S]*?\$this->belongsTo\(\s*([A-Za-z0-9_\\\\:]+)::class/s',
            'belongsToMany' => '/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s*{[\s\S]*?\$this->belongsToMany\(\s*([A-Za-z0-9_\\\\:]+)::class/s',
            'morphOne' => '/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s*{[\s\S]*?\$this->morphOne\(\s*([A-Za-z0-9_\\\\:]+)::class/s',
            'morphMany' => '/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s*{[\s\S]*?\$this->morphMany\(\s*([A-Za-z0-9_\\\\:]+)::class/s',
            'morphToMany' => '/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s*{[\s\S]*?\$this->morphToMany\(\s*([A-Za-z0-9_\\\\:]+)::class/s',
        ];

        $found = [];
        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) !== false) {
                foreach ($matches as $match) {
                    $found[] = [
                        'type' => $type,
                        'method' => $match[1],
                        'related' => trim($match[2], '\\'),
                    ];
                }
            }
        }

        return $found;
    }

    /**
     * @return array<int, Node\Stmt>|null
     */
    private function parseAst(string $source): ?array
    {
        try {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();

            return $parser->parse($source);
        } catch (Error) {
            return null;
        }
    }

    /**
     * @param array<int, Node\Stmt> $ast
     * @return array{
     *   namespace: string|null,
     *   class: string|null,
     *   kind: string|null,
     *   extends: string|null,
     *   implements: list<string>,
     *   public_methods: int,
     *   constructor_dependencies: list<string>
     * }|null
     */
    private function parseAstClassMeta(array $ast): ?array
    {
        $namespace = null;
        foreach ($ast as $stmt) {
            if ($stmt instanceof Namespace_) {
                $namespace = $stmt->name?->toString();
                break;
            }
        }

        $finder = new NodeFinder();
        /** @var ClassLike|null $classLike */
        $classLike = $finder->findFirst($ast, static fn (Node $node): bool => $node instanceof ClassLike && $node->name !== null);
        if ($classLike === null || $classLike->name === null) {
            return null;
        }

        $kind = match (true) {
            $classLike instanceof Node\Stmt\Class_ => 'class',
            $classLike instanceof Node\Stmt\Interface_ => 'interface',
            $classLike instanceof Node\Stmt\Trait_ => 'trait',
            default => null,
        };

        $implements = [];
        if ($classLike instanceof Node\Stmt\Class_) {
            foreach ($classLike->implements as $impl) {
                $implements[] = $impl->toString();
            }
        } elseif ($classLike instanceof Node\Stmt\Interface_) {
            foreach ($classLike->extends as $extended) {
                $implements[] = $extended->toString();
            }
        }

        $extends = null;
        if ($classLike instanceof Node\Stmt\Class_ && $classLike->extends !== null) {
            $extends = $classLike->extends->toString();
        }

        $publicMethods = 0;
        $constructorDependencies = [];
        foreach ($classLike->getMethods() as $method) {
            if ($method->isPublic()) {
                $publicMethods++;
            }
            if ($method->name->toString() !== '__construct') {
                continue;
            }

            foreach ($method->params as $param) {
                $type = $param->type;
                if ($type instanceof Name) {
                    $constructorDependencies[] = $type->toString();
                } elseif ($type instanceof Identifier) {
                    $constructorDependencies[] = $type->toString();
                }
            }
        }

        return [
            'namespace' => $namespace,
            'class' => $classLike->name->toString(),
            'kind' => $kind,
            'extends' => $extends,
            'implements' => array_values(array_unique($implements)),
            'public_methods' => $publicMethods,
            'constructor_dependencies' => array_values(array_unique($constructorDependencies)),
        ];
    }

    /**
     * @param array<int, Node\Stmt> $ast
     * @return list<array{type: string, related: string, method: string}>
     */
    private function parseAstRelationships(array $ast): array
    {
        $finder = new NodeFinder();
        /** @var list<ClassMethod> $methods */
        $methods = $finder->findInstanceOf($ast, ClassMethod::class);
        $relationshipMethods = ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphOne', 'morphMany', 'morphToMany'];
        $found = [];

        foreach ($methods as $method) {
            if ($method->stmts === null) {
                continue;
            }
            /** @var list<MethodCall> $calls */
            $calls = $finder->findInstanceOf($method->stmts, MethodCall::class);
            foreach ($calls as $call) {
                if (!$call->var instanceof Variable || $call->var->name !== 'this') {
                    continue;
                }
                if (!$call->name instanceof Identifier) {
                    continue;
                }

                $relationship = $call->name->toString();
                if (!in_array($relationship, $relationshipMethods, true)) {
                    continue;
                }

                $related = $this->extractRelatedClass($call->args);
                if ($related === null) {
                    continue;
                }

                $found[] = [
                    'type' => $relationship,
                    'related' => trim($related, '\\'),
                    'method' => $method->name->toString(),
                ];
            }
        }

        return $found;
    }

    /**
     * @param array<int, Arg|VariadicPlaceholder> $args
     */
    private function extractRelatedClass(array $args): ?string
    {
        if (!isset($args[0])) {
            return null;
        }
        if (!$args[0] instanceof Arg) {
            return null;
        }
        $value = $args[0]->value;
        if (!$value instanceof ClassConstFetch) {
            return null;
        }
        if (!$value->class instanceof Name) {
            return null;
        }
        if (!$value->name instanceof Identifier || strtolower($value->name->toString()) !== 'class') {
            return null;
        }

        return $value->class->toString();
    }

    /**
     * @return array{
     *   namespace: string|null,
     *   class: string|null,
     *   kind: string|null,
     *   extends: string|null,
     *   implements: list<string>,
     *   public_methods: int,
     *   constructor_dependencies: list<string>
     * }
     */
    private function emptyResult(): array
    {
        return [
            'namespace' => null,
            'class' => null,
            'kind' => null,
            'extends' => null,
            'implements' => [],
            'public_methods' => 0,
            'constructor_dependencies' => [],
        ];
    }
}
