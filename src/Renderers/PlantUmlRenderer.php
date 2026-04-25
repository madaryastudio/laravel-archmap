<?php

namespace Ardana\Archmap\Renderers;

use Ardana\Archmap\Contracts\Renderer;
use Ardana\Archmap\Graph\Graph;

final class PlantUmlRenderer implements Renderer
{
    public function render(Graph $graph, array $context = []): string
    {
        $diagram = (string) ($context['diagram'] ?? 'classes');

        return match ($diagram) {
            'erd' => $this->renderErd($graph),
            'routes' => $this->renderRoutes($graph),
            'components' => $this->renderComponents($graph),
            'sequence' => $this->renderSequence($graph, (string) ($context['route'] ?? '')),
            default => $this->renderClasses($graph),
        };
    }

    private function renderClasses(Graph $graph): string
    {
        $lines = ['@startuml'];
        foreach ($graph->nodesByType('class') as $node) {
            $lines[] = 'class '.$this->safe($node->name);
        }
        foreach ($graph->edges() as $edge) {
            if ($edge->type === 'extends') {
                $from = $this->labelFor($graph, $edge->from);
                $to = $this->labelFor($graph, $edge->to);
                $lines[] = $this->safe($from).' --|> '.$this->safe($to);
            }
            if ($edge->type === 'depends_on') {
                $from = $this->labelFor($graph, $edge->from);
                $to = $this->labelFor($graph, $edge->to);
                $lines[] = $this->safe($from).' ..> '.$this->safe($to);
            }
        }
        $lines[] = '@enduml';

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function renderRoutes(Graph $graph): string
    {
        $lines = ['@startuml'];
        foreach ($graph->nodesByType('route') as $route) {
            $lines[] = 'usecase "'.$route->name.'" as '.$this->safeId($route->id);
        }
        foreach ($graph->edgesByType('routes_to') as $edge) {
            $route = $this->safeId($edge->from);
            $controller = $this->safe($this->labelFor($graph, $edge->to));
            $lines[] = 'class '.$controller;
            $lines[] = $route.' --> '.$controller;
        }
        $lines[] = '@enduml';

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function renderErd(Graph $graph): string
    {
        $lines = ['@startuml', 'hide methods', 'hide stereotypes'];
        foreach ($graph->nodesByType('model') as $model) {
            $lines[] = 'entity '.$this->safe($model->name);
        }
        foreach ($graph->edges() as $edge) {
            if (!in_array($edge->type, ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphOne', 'morphMany', 'morphToMany'], true)) {
                continue;
            }
            $from = $this->safe($this->labelFor($graph, $edge->from));
            $to = $this->safe($this->labelFor($graph, $edge->to));
            $lines[] = $from.' ||--o{ '.$to.' : '.$edge->type;
        }
        $lines[] = '@enduml';

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function renderComponents(Graph $graph): string
    {
        $lines = ['@startuml'];
        foreach ($graph->nodesByType('class') as $class) {
            $role = (string) ($class->metadata['role'] ?? 'component');
            $st = $role !== '' ? ' <<'.$role.'>>' : '';
            $lines[] = 'component '.$this->safe($class->name).$st;
        }
        foreach ($graph->edges() as $edge) {
            if (!in_array($edge->type, ['routes_to', 'depends_on'], true)) {
                continue;
            }
            $from = $this->safe($this->labelFor($graph, $edge->from));
            $to = $this->safe($this->labelFor($graph, $edge->to));
            $lines[] = $from.' --> '.$to;
        }
        $lines[] = '@enduml';

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function renderSequence(Graph $graph, string $routeFilter): string
    {
        $lines = ['@startuml', 'actor Client', 'participant Route'];
        $routeName = $routeFilter;
        if ($routeName === '') {
            $routes = $graph->nodesByType('route');
            $routeName = $routes[0]->name ?? 'Request';
        }
        $lines[] = 'Client -> Route: '.$routeName;
        foreach ($graph->edgesByType('routes_to') as $edge) {
            $routeLabel = $this->labelFor($graph, $edge->from);
            if ($routeFilter !== '' && strcasecmp($routeLabel, $routeFilter) !== 0) {
                continue;
            }
            $controller = $this->safe($this->labelFor($graph, $edge->to));
            $lines[] = 'participant '.$controller;
            $lines[] = 'Route -> '.$controller.': '.($edge->label ?: 'handle');
            $lines[] = $controller.' --> Client: response';
            break;
        }
        $lines[] = '@enduml';

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function labelFor(Graph $graph, string $id): string
    {
        foreach ($graph->nodes() as $node) {
            if ($node->id === $id) {
                return $node->name;
            }
        }

        return $id;
    }

    private function safe(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '_', $value) ?? 'Unknown';
    }

    private function safeId(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '_', $value) ?? 'ID';
    }
}
