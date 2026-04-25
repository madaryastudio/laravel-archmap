<?php

namespace Ardana\Archmap\Analyzers;

use Ardana\Archmap\Graph\Graph;
use Ardana\Archmap\Graph\Node;
use Illuminate\Contracts\Config\Repository;

final class HealthAnalyzer
{
    public function __construct(private readonly Repository $config)
    {
    }

    /**
     * @param array<string, int> $stats
     * @return list<array{severity: string, code: string, message: string, recommendation: string}>
     */
    public function analyze(Graph $graph, array $stats = []): array
    {
        /** @var array<string, int> $thresholds */
        $thresholds = (array) $this->config->get('archmap.report.thresholds', []);
        $maxControllerMethods = (int) ($thresholds['max_controller_public_methods'] ?? 10);
        $maxServiceDeps = (int) ($thresholds['max_service_dependencies'] ?? 7);
        $maxModelRelationships = (int) ($thresholds['max_model_relationships'] ?? 12);

        $issues = [];

        foreach ($graph->nodesByType('class') as $classNode) {
            $role = (string) ($classNode->metadata['role'] ?? '');
            $publicMethods = (int) ($classNode->metadata['public_methods'] ?? 0);
            $deps = $classNode->metadata['constructor_dependencies'] ?? [];
            $depCount = is_array($deps) ? count($deps) : 0;

            if ($role === 'controllers' && $publicMethods > $maxControllerMethods) {
                $issues[] = $this->issue(
                    'warning',
                    'CONTROLLER_TOO_MANY_METHODS',
                    sprintf('%s has %d public methods.', $classNode->name, $publicMethods),
                    'Split endpoints or move business logic to service classes.'
                );
            }

            if ($role === 'services' && $depCount > $maxServiceDeps) {
                $issues[] = $this->issue(
                    'warning',
                    'SERVICE_TOO_MANY_DEPENDENCIES',
                    sprintf('%s has %d constructor dependencies.', $classNode->name, $depCount),
                    'Reduce constructor dependencies and split responsibilities.'
                );
            }
        }

        $relationshipCount = [];
        foreach ($graph->edges() as $edge) {
            if (!in_array($edge->type, ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphOne', 'morphMany', 'morphToMany'], true)) {
                continue;
            }
            $relationshipCount[$edge->from] = ($relationshipCount[$edge->from] ?? 0) + 1;
        }

        foreach ($graph->nodesByType('model') as $modelNode) {
            $count = $relationshipCount[$modelNode->id] ?? 0;
            if ($count > $maxModelRelationships) {
                $issues[] = $this->issue(
                    'warning',
                    'MODEL_TOO_MANY_RELATIONSHIPS',
                    sprintf('%s has %d relationships.', $modelNode->name, $count),
                    'Review aggregate boundaries or split model responsibilities.'
                );
            }
        }

        $issues[] = $this->issue('info', 'ROUTES_COUNT', sprintf('%d routes detected.', $stats['routes'] ?? 0), 'No action required.');
        $issues[] = $this->issue('info', 'MODELS_COUNT', sprintf('%d models detected.', $stats['models'] ?? 0), 'No action required.');
        $issues[] = $this->issue('info', 'CLASSES_COUNT', sprintf('%d classes detected.', $stats['classes'] ?? 0), 'No action required.');

        return $issues;
    }

    /**
     * @return array{severity: string, code: string, message: string, recommendation: string}
     */
    private function issue(string $severity, string $code, string $message, string $recommendation): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'recommendation' => $recommendation,
        ];
    }
}
