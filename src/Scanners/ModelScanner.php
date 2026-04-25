<?php

namespace Ardana\Archmap\Scanners;

use Ardana\Archmap\Contracts\Scanner;
use Ardana\Archmap\Graph\Edge;
use Ardana\Archmap\Graph\Graph;
use Ardana\Archmap\Graph\Node;
use Ardana\Archmap\Support\FileFinder;
use Ardana\Archmap\Support\PhpFileParser;
use Illuminate\Contracts\Config\Repository;

final class ModelScanner implements Scanner
{
    public function __construct(
        private readonly Repository $config,
        private readonly FileFinder $fileFinder,
        private readonly PhpFileParser $parser,
    ) {
    }

    public function scan(Graph $graph): array
    {
        $path = (string) $this->config->get('archmap.paths.models', app_path('Models'));
        /** @var list<string> $ignore */
        $ignore = (array) $this->config->get('archmap.ignore.paths', []);
        $files = $this->fileFinder->phpFiles($path, $ignore);

        $stats = ['models' => 0, 'relationships' => 0];
        $warnings = [];
        $knownModels = [];

        foreach ($files as $file) {
            $meta = $this->parser->parse($file);
            if ($meta['class'] === null || $meta['kind'] !== 'class') {
                continue;
            }

            $namespace = $meta['namespace'] ?? 'App\\Models';
            $class = $namespace.'\\'.$meta['class'];
            $id = 'model:'.$class;
            $knownModels[$meta['class']] = $id;
            $knownModels[$class] = $id;

            $graph->addNode(new Node(
                id: $id,
                type: 'model',
                name: $meta['class'],
                namespace: $namespace,
                path: $file,
                metadata: ['public_methods' => $meta['public_methods']]
            ));

            $stats['models']++;
        }

        foreach ($files as $file) {
            $meta = $this->parser->parse($file);
            if ($meta['class'] === null || $meta['kind'] !== 'class') {
                continue;
            }

            $namespace = $meta['namespace'] ?? 'App\\Models';
            $fromId = $knownModels[$namespace.'\\'.$meta['class']] ?? null;
            if ($fromId === null) {
                continue;
            }

            foreach ($this->parser->findRelationships($file) as $relationship) {
                $related = trim($relationship['related'], '\\');
                $targetId = $knownModels[$related] ?? $knownModels[class_basename($related)] ?? null;

                if ($targetId === null) {
                    $warnings[] = sprintf(
                        'Model relationship ambiguous: %s -> %s',
                        $meta['class'],
                        $relationship['related']
                    );
                    continue;
                }

                $graph->addEdge(new Edge(
                    from: $fromId,
                    to: $targetId,
                    type: $relationship['type'],
                    label: $relationship['method']
                ));
                $stats['relationships']++;
            }
        }

        return ['stats' => $stats, 'warnings' => array_values(array_unique($warnings))];
    }
}
