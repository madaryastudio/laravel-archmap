<?php

namespace Ardana\Archmap\Renderers;

use Ardana\Archmap\Contracts\Renderer;
use Ardana\Archmap\Graph\Graph;

final class JsonRenderer implements Renderer
{
    public function render(Graph $graph, array $context = []): string
    {
        $payload = [
            'metadata' => [
                'generated_at' => $context['generated_at'] ?? now()->toIso8601String(),
                'package_version' => $context['package_version'] ?? '0.1.0',
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
            ],
            'nodes' => array_map(static fn (array $n): array => $n, $graph->toArray()['nodes']),
            'edges' => array_map(static fn (array $e): array => $e, $graph->toArray()['edges']),
            'issues' => $context['issues'] ?? [],
            'stats' => $context['stats'] ?? [],
            'warnings' => $context['warnings'] ?? [],
        ];

        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
}
