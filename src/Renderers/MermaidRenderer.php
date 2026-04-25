<?php

namespace Ardana\Archmap\Renderers;

use Ardana\Archmap\Contracts\Renderer;
use Ardana\Archmap\Graph\Graph;
use Ardana\Archmap\Graph\Node;

final class MermaidRenderer implements Renderer
{
    public function render(Graph $graph, array $context = []): string
    {
        $type = (string) ($context['diagram'] ?? 'routes');

        return match ($type) {
            'erd' => $this->renderErd($graph),
            'classes' => $this->renderClasses($graph),
            'components' => $this->renderComponents($graph),
            'sequence' => $this->renderSequence($graph, $context),
            default => $this->renderRoutes($graph),
        };
    }

    private function renderRoutes(Graph $graph): string
    {
        $lines = ['flowchart TD'];
        foreach ($graph->edgesByType('routes_to') as $edge) {
            $from = $this->nodeOrFallback($graph, $edge->from);
            $to = $this->nodeOrFallback($graph, $edge->to);
            $lines[] = sprintf(
                '    %s["%s"] --> %s["%s"]',
                $this->safeId($from['id']),
                $this->escape($from['label']),
                $this->safeId($to['id']),
                $this->escape($to['label'])
            );
        }

        if (count($lines) === 1) {
            $lines[] = '    Empty["No routes found"]';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderSequence(Graph $graph, array $context): string
    {
        $requestedRoute = trim((string) ($context['route'] ?? ''));
        $routeNode = null;

        foreach ($graph->nodesByType('route') as $node) {
            if ($requestedRoute === '' || strcasecmp($node->name, $requestedRoute) === 0 || str_contains($node->name, $requestedRoute)) {
                $routeNode = $node;
                break;
            }
        }

        if ($routeNode === null) {
            return implode(PHP_EOL, [
                'sequenceDiagram',
                '    participant Client',
                '    participant Route',
                '    Client->>Route: Request',
                '    Note over Route: No matching route found',
                '',
            ]);
        }

        $controllerId = null;
        $controllerMethod = 'handle()';
        foreach ($graph->edgesByType('routes_to') as $edge) {
            if ($edge->from === $routeNode->id) {
                $controllerId = $edge->to;
                if ($edge->label !== null && $edge->label !== '') {
                    $controllerMethod = $edge->label.'()';
                }
                break;
            }
        }

        $controller = $controllerId ? $this->findNodeById($graph, $controllerId) : null;
        $lines = ['sequenceDiagram', '    participant Client', '    participant Route'];
        $lines[] = '    Client->>Route: '.$this->escape($routeNode->name);

        $confidence = 0.3;
        if ($controller !== null) {
            $lines[] = '    participant '.$this->safeClassName($controller->name);
            $lines[] = sprintf(
                '    Route->>%s: %s',
                $this->safeClassName($controller->name),
                $this->escape($controllerMethod)
            );
            $confidence += 0.4;

            $deps = $controller->metadata['constructor_dependencies'] ?? [];
            if (is_array($deps)) {
                foreach ($deps as $dep) {
                    if (!is_string($dep) || $dep === '') {
                        continue;
                    }
                    $depNode = $this->findClassByName($graph, $dep);
                    $depName = $depNode !== null ? $depNode->name : class_basename($dep);
                    $lines[] = '    participant '.$this->safeClassName($depName);
                    $lines[] = sprintf(
                        '    %s->>%s: call()',
                        $this->safeClassName($controller->name),
                        $this->safeClassName($depName)
                    );
                }
                if (count($deps) > 0) {
                    $confidence += 0.2;
                }
            }

            $lines[] = sprintf(
                '    %s-->>Client: response',
                $this->safeClassName($controller->name)
            );
        } else {
            $lines[] = '    Route-->>Client: response';
        }

        $confidence = min(1.0, $confidence);
        $lines[] = sprintf('    Note over Route: confidence %.2f', $confidence);

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function renderComponents(Graph $graph): string
    {
        $lines = ['flowchart TD'];
        $roles = [
            'controllers' => 'Controllers',
            'services' => 'Services',
            'repositories' => 'Repositories',
            'jobs' => 'Jobs',
            'events' => 'Events',
            'listeners' => 'Listeners',
            'policies' => 'Policies',
            'models' => 'Models',
        ];

        foreach ($roles as $roleKey => $label) {
            $nodes = array_values(array_filter(
                $graph->nodesByType('class'),
                static fn ($n): bool => (($n->metadata['role'] ?? '') === $roleKey)
            ));
            if ($nodes === []) {
                continue;
            }
            $lines[] = '    subgraph '.$this->safeId($label).'["'.$label.'"]';
            foreach ($nodes as $node) {
                $lines[] = '        '.$this->safeId($node->id).'["'.$this->escape($node->name).'"]';
            }
            $lines[] = '    end';
        }

        foreach ($graph->edges() as $edge) {
            if (!in_array($edge->type, ['routes_to', 'depends_on'], true)) {
                continue;
            }
            $from = $this->nodeOrFallback($graph, $edge->from);
            $to = $this->nodeOrFallback($graph, $edge->to);
            $label = $edge->label ? '|'.$this->escape($edge->label).'|' : '';
            $lines[] = sprintf(
                '    %s -->%s %s',
                $this->safeId($from['id']),
                $label,
                $this->safeId($to['id'])
            );
        }

        if (count($lines) === 1) {
            $lines[] = '    Empty["No components found"]';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function renderErd(Graph $graph): string
    {
        $lines = ['erDiagram'];
        foreach ($graph->edges() as $edge) {
            if (!in_array($edge->type, ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphOne', 'morphMany', 'morphToMany'], true)) {
                continue;
            }
            $from = $this->nodeOrFallback($graph, $edge->from);
            $to = $this->nodeOrFallback($graph, $edge->to);
            $lines[] = sprintf(
                '    %s ||--o{ %s : %s',
                strtoupper($this->sanitizeName($from['label'])),
                strtoupper($this->sanitizeName($to['label'])),
                $edge->type
            );
        }

        if (count($lines) === 1) {
            $lines[] = '    EMPTY ||--o{ EMPTY : none';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function renderClasses(Graph $graph): string
    {
        $lines = ['classDiagram'];
        foreach ($graph->nodesByType('class') as $node) {
            $lines[] = sprintf('    class %s', $this->safeClassName($node->name));
        }

        foreach ($graph->edges() as $edge) {
            if (!in_array($edge->type, ['extends', 'depends_on'], true)) {
                continue;
            }
            $from = $this->nodeOrFallback($graph, $edge->from);
            $to = $this->nodeOrFallback($graph, $edge->to);
            $arrow = $edge->type === 'extends' ? '--|>' : '..>';
            $lines[] = sprintf(
                '    %s %s %s',
                $this->safeClassName($from['label']),
                $arrow,
                $this->safeClassName($to['label'])
            );
        }

        if (count($lines) === 1) {
            $lines[] = '    class Empty';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @return array{id: string, label: string}
     */
    private function nodeOrFallback(Graph $graph, string $nodeId): array
    {
        foreach ($graph->nodes() as $node) {
            if ($node->id === $nodeId) {
                return ['id' => $node->id, 'label' => $node->name];
            }
        }

        return ['id' => $nodeId, 'label' => $nodeId];
    }

    private function findNodeById(Graph $graph, string $id): ?Node
    {
        foreach ($graph->nodes() as $node) {
            if ($node->id === $id) {
                return $node;
            }
        }

        return null;
    }

    private function findClassByName(Graph $graph, string $className): ?Node
    {
        $className = trim($className, '\\');
        $basename = class_basename($className);
        foreach ($graph->nodesByType('class') as $node) {
            $fqn = ($node->namespace ? $node->namespace.'\\' : '').$node->name;
            if (strcasecmp($fqn, $className) === 0 || strcasecmp($node->name, $basename) === 0) {
                return $node;
            }
        }

        return null;
    }

    private function safeId(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '_', $value) ?? 'N';
    }

    private function safeClassName(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '_', $value) ?? 'Unknown';
    }

    private function sanitizeName(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '_', $value) ?? 'UNKNOWN';
    }

    private function escape(string $value): string
    {
        return str_replace('"', '\"', $value);
    }
}
