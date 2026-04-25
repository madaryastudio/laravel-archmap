<?php

namespace Ardana\Archmap\Services;

use Ardana\Archmap\Contracts\Scanner;
use Ardana\Archmap\Graph\Graph;
use Illuminate\Contracts\Container\Container;

final class ScannerManager
{
    /**
     * @param array<string, class-string<Scanner>> $scannerMap
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $scannerMap,
    ) {
    }

    /**
     * @param list<string> $targets
     * @return array{graph: Graph, stats: array<string, int>, warnings: list<string>}
     */
    public function scan(array $targets): array
    {
        $graph = new Graph();
        $stats = [];
        $warnings = [];

        foreach ($targets as $target) {
            if (!isset($this->scannerMap[$target])) {
                continue;
            }

            /** @var Scanner $scanner */
            $scanner = $this->container->make($this->scannerMap[$target]);
            $result = $scanner->scan($graph);

            foreach ($result['stats'] as $key => $value) {
                $stats[$key] = ($stats[$key] ?? 0) + (int) $value;
            }

            foreach ($result['warnings'] as $warning) {
                $warnings[] = (string) $warning;
            }
        }

        return ['graph' => $graph, 'stats' => $stats, 'warnings' => array_values(array_unique($warnings))];
    }
}
