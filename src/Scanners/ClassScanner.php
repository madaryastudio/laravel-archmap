<?php

namespace Ardana\Archmap\Scanners;

use Ardana\Archmap\Contracts\Scanner;
use Ardana\Archmap\Graph\Edge;
use Ardana\Archmap\Graph\Graph;
use Ardana\Archmap\Graph\Node;
use Ardana\Archmap\Support\FileFinder;
use Ardana\Archmap\Support\PhpFileParser;
use Illuminate\Contracts\Config\Repository;

final class ClassScanner implements Scanner
{
    public function __construct(
        private readonly Repository $config,
        private readonly FileFinder $fileFinder,
        private readonly PhpFileParser $parser,
    ) {
    }

    public function scan(Graph $graph): array
    {
        /** @var array<string, string> $paths */
        $paths = (array) $this->config->get('archmap.paths', []);
        /** @var list<string> $ignore */
        $ignore = (array) $this->config->get('archmap.ignore.paths', []);

        $stats = ['classes' => 0];
        $warnings = [];
        $classMap = [];

        foreach ($paths as $role => $path) {
            foreach ($this->fileFinder->phpFiles($path, $ignore) as $file) {
                $meta = $this->parser->parse($file);
                if ($meta['class'] === null || $meta['kind'] !== 'class') {
                    continue;
                }

                $namespace = $meta['namespace'] ?? null;
                $fqn = $namespace ? $namespace.'\\'.$meta['class'] : $meta['class'];
                $nodeId = 'class:'.$fqn;
                $classMap[$fqn] = $nodeId;
                $classMap[$meta['class']] = $nodeId;

                $graph->addNode(new Node(
                    id: $nodeId,
                    type: 'class',
                    name: $meta['class'],
                    namespace: $namespace,
                    path: $file,
                    metadata: [
                        'role' => $role,
                        'extends' => $meta['extends'],
                        'public_methods' => $meta['public_methods'],
                        'constructor_dependencies' => $meta['constructor_dependencies'],
                    ]
                ));

                $stats['classes']++;
            }
        }

        foreach ($graph->nodesByType('class') as $node) {
            $extends = $node->metadata['extends'] ?? null;
            if (is_string($extends) && isset($classMap[$extends])) {
                $graph->addEdge(new Edge($node->id, $classMap[$extends], 'extends'));
            }

            $deps = $node->metadata['constructor_dependencies'] ?? [];
            if (!is_array($deps)) {
                continue;
            }

            foreach ($deps as $dep) {
                if (is_string($dep) && isset($classMap[$dep])) {
                    $graph->addEdge(new Edge($node->id, $classMap[$dep], 'depends_on'));
                }
            }
        }

        return ['stats' => $stats, 'warnings' => $warnings];
    }
}
