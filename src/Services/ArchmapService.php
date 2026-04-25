<?php

namespace Ardana\Archmap\Services;

use Ardana\Archmap\Analyzers\HealthAnalyzer;
use Ardana\Archmap\Graph\Graph;
use Ardana\Archmap\Support\FileFinder;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;

final class ArchmapService
{
    public function __construct(
        private readonly Repository $config,
        private readonly Filesystem $files,
        private readonly ScannerManager $scannerManager,
        private readonly RendererManager $rendererManager,
        private readonly HealthAnalyzer $healthAnalyzer,
        private readonly CacheStore $cacheStore,
        private readonly FileFinder $fileFinder,
        private readonly Router $router,
    ) {
    }

    /**
     * @param list<string> $scanners
     * @return array{
     *   stats: array<string, int>,
     *   warnings: list<string>,
     *   issues: list<array<string, string>>,
     *   files: array<string, string>,
     *   format: string,
     *   from_cache: bool
     * }
     */
    public function generate(
        array $scanners = ['routes', 'models', 'classes'],
        bool $write = true,
        string $format = 'mermaid',
        bool $fresh = false
    ): array {
        $format = $this->normalizeFormat($format);
        $cacheKey = sprintf('generate:%s:%s', implode(',', $scanners), $format);
        $fingerprint = $this->fingerprint($scanners);
        $paths = $this->diagramPaths($format);

        if (!$fresh) {
            $cached = $this->readCache($cacheKey, $fingerprint);
            if ($cached !== null) {
                $cachedOutputs = $this->toStringMap($cached['outputs'] ?? []);
                $cachedFiles = $this->toStringMap($cached['files'] ?? []);
                if ($write) {
                    $this->writeOutputs($cachedOutputs, $cachedFiles);
                }
                /** @var array<string, mixed> $cachedResult */
                $cachedResult = is_array($cached['result'] ?? null) ? $cached['result'] : [];

                return [
                    'stats' => $this->toStats($cachedResult['stats'] ?? []),
                    'warnings' => $this->toStringList($cachedResult['warnings'] ?? []),
                    'issues' => $this->toIssues($cachedResult['issues'] ?? []),
                    'files' => $cachedFiles !== [] ? $cachedFiles : $paths,
                    'format' => is_string($cachedResult['format'] ?? null) ? $cachedResult['format'] : $format,
                    'from_cache' => true,
                ];
            }
        }

        $scan = $this->scanGraph($scanners);
        $graph = $scan['graph'];
        $stats = $scan['stats'];
        $warnings = $scan['warnings'];
        $issues = $this->healthAnalyzer->analyze($graph, $stats);
        $generatedAt = now()->toIso8601String();

        $routesMermaid = $this->rendererManager->render('mermaid', $graph, ['diagram' => 'routes']);
        $erdMermaid = $this->rendererManager->render('mermaid', $graph, ['diagram' => 'erd']);
        $classesMermaid = $this->rendererManager->render('mermaid', $graph, ['diagram' => 'classes']);
        $componentsMermaid = $this->rendererManager->render('mermaid', $graph, ['diagram' => 'components']);

        $diagramRenderer = $format === 'plantuml' ? 'plantuml' : 'mermaid';
        $outputs = [
            'architecture' => $this->rendererManager->render('markdown', $graph, [
                'generated_at' => $generatedAt,
                'routes_mmd' => $routesMermaid,
                'erd_mmd' => $erdMermaid,
                'classes_mmd' => $classesMermaid,
                'components_mmd' => $componentsMermaid,
                'issues' => $issues,
                'stats' => $stats,
            ]),
            'routes' => $this->rendererManager->render($diagramRenderer, $graph, ['diagram' => 'routes']),
            'erd' => $this->rendererManager->render($diagramRenderer, $graph, ['diagram' => 'erd']),
            'classes' => $this->rendererManager->render($diagramRenderer, $graph, ['diagram' => 'classes']),
            'components' => $this->rendererManager->render($diagramRenderer, $graph, ['diagram' => 'components']),
            'json' => $this->rendererManager->render('json', $graph, [
                'generated_at' => $generatedAt,
                'issues' => $issues,
                'stats' => $stats,
                'warnings' => $warnings,
            ]),
        ];

        if ($write) {
            $this->writeOutputs($outputs, $paths);
        }

        $result = [
            'stats' => $stats,
            'warnings' => $warnings,
            'issues' => $issues,
            'files' => $paths,
            'format' => $format,
            'from_cache' => false,
        ];

        $this->writeCache($cacheKey, $fingerprint, $result, $outputs);

        return $result;
    }

    /**
     * @return array{
     *   stats: array<string, int>,
     *   warnings: list<string>,
     *   files: array<string, string>,
     *   format: string,
     *   from_cache: bool
     * }
     */
    public function generateComponents(bool $write = true, string $format = 'mermaid', bool $fresh = false): array
    {
        $format = $this->normalizeFormat($format);
        $scanners = ['routes', 'classes', 'models'];
        $cacheKey = 'components:'.$format;
        $fingerprint = $this->fingerprint($scanners);
        $paths = $this->diagramPaths($format);

        if (!$fresh) {
            $cached = $this->readCache($cacheKey, $fingerprint);
            if ($cached !== null) {
                /** @var array<string, mixed> $cachedResult */
                $cachedResult = is_array($cached['result'] ?? null) ? $cached['result'] : [];
                if ($write) {
                    $this->writeOutputs($this->toStringMap($cached['outputs'] ?? []), ['components' => $paths['components']]);
                }

                return [
                    'stats' => $this->toStats($cachedResult['stats'] ?? []),
                    'warnings' => $this->toStringList($cachedResult['warnings'] ?? []),
                    'files' => ['components' => $paths['components']],
                    'format' => $format,
                    'from_cache' => true,
                ];
            }
        }

        $scan = $this->scanGraph($scanners);
        $renderer = $format === 'plantuml' ? 'plantuml' : 'mermaid';
        $componentText = $this->rendererManager->render($renderer, $scan['graph'], ['diagram' => 'components']);
        if ($write) {
            $this->writeOutputs(['components' => $componentText], ['components' => $paths['components']]);
        }

        $result = [
            'stats' => $scan['stats'],
            'warnings' => $scan['warnings'],
            'files' => ['components' => $paths['components']],
            'format' => $format,
            'from_cache' => false,
        ];
        $this->writeCache($cacheKey, $fingerprint, $result, ['components' => $componentText]);

        return $result;
    }

    /**
     * @return array{
     *   stats: array<string, int>,
     *   warnings: list<string>,
     *   files: array<string, string>,
     *   route: string,
     *   format: string,
     *   from_cache: bool
     * }
     */
    public function generateSequence(
        ?string $routeFilter = null,
        bool $write = true,
        string $format = 'mermaid',
        bool $fresh = false
    ): array {
        $format = $this->normalizeFormat($format);
        $scanners = ['routes', 'classes', 'models'];
        $cacheKey = 'sequence:'.$format.':'.trim((string) $routeFilter);
        $fingerprint = $this->fingerprint($scanners);

        $scan = $this->scanGraph($scanners);
        $selectedRoute = $this->resolveRouteFilter($scan['graph'], $routeFilter);
        $sequencePath = $this->sequencePath($selectedRoute, $format);

        if (!$fresh) {
            $cached = $this->readCache($cacheKey, $fingerprint);
            if ($cached !== null) {
                /** @var array<string, mixed> $cachedResult */
                $cachedResult = is_array($cached['result'] ?? null) ? $cached['result'] : [];
                if ($write) {
                    $this->writeOutputs($this->toStringMap($cached['outputs'] ?? []), ['sequence' => $sequencePath]);
                }

                return [
                    'stats' => $this->toStats($cachedResult['stats'] ?? []),
                    'warnings' => $this->toStringList($cachedResult['warnings'] ?? []),
                    'files' => ['sequence' => $sequencePath],
                    'route' => $selectedRoute,
                    'format' => $format,
                    'from_cache' => true,
                ];
            }
        }

        $renderer = $format === 'plantuml' ? 'plantuml' : 'mermaid';
        $sequenceText = $this->rendererManager->render($renderer, $scan['graph'], [
            'diagram' => 'sequence',
            'route' => $selectedRoute,
        ]);
        if ($write) {
            $this->writeOutputs(['sequence' => $sequenceText], ['sequence' => $sequencePath]);
        }

        $result = [
            'stats' => $scan['stats'],
            'warnings' => $scan['warnings'],
            'files' => ['sequence' => $sequencePath],
            'route' => $selectedRoute,
            'format' => $format,
            'from_cache' => false,
        ];
        $this->writeCache($cacheKey, $fingerprint, $result, ['sequence' => $sequenceText]);

        return $result;
    }

    /**
     * @param list<string> $scanners
     * @return array{graph: Graph, stats: array<string, int>, warnings: list<string>}
     */
    private function scanGraph(array $scanners): array
    {
        return $this->scannerManager->scan($scanners);
    }

    /**
     * @param list<string> $scanners
     */
    private function fingerprint(array $scanners): string
    {
        /** @var array<string, string> $paths */
        $paths = (array) $this->config->get('archmap.paths', []);
        /** @var list<string> $ignore */
        $ignore = (array) $this->config->get('archmap.ignore.paths', []);

        $tokens = ['scanners:'.implode(',', $scanners)];
        foreach ($paths as $path) {
            foreach ($this->fileFinder->phpFiles((string) $path, $ignore) as $file) {
                $tokens[] = $file.':'.@filemtime($file).':'.@filesize($file);
            }
        }

        $routesPath = base_path('routes');
        foreach ($this->fileFinder->phpFiles($routesPath, $ignore) as $file) {
            $tokens[] = $file.':'.@filemtime($file).':'.@filesize($file);
        }

        $routes = $this->router->getRoutes()->getRoutes();
        usort($routes, static fn ($a, $b): int => [$a->uri(), implode('|', $a->methods())] <=> [$b->uri(), implode('|', $b->methods())]);
        foreach ($routes as $route) {
            $tokens[] = implode('|', $route->methods()).' '.$route->uri().' '.$route->getActionName();
        }

        sort($tokens);

        return sha1(implode('||', $tokens));
    }

    /**
     * @return array<string, string>
     */
    private function diagramPaths(string $format): array
    {
        $outputPath = (string) $this->config->get('archmap.output_path', base_path('docs'));
        $diagramsPath = (string) $this->config->get('archmap.diagrams_path', base_path('docs/diagrams'));
        $extension = $format === 'plantuml' ? 'puml' : 'mmd';

        return [
            'architecture' => $outputPath.'/architecture.md',
            'erd' => $diagramsPath.'/erd.'.$extension,
            'routes' => $diagramsPath.'/routes.'.$extension,
            'classes' => $diagramsPath.'/classes.'.$extension,
            'components' => $diagramsPath.'/components.'.$extension,
            'json' => $outputPath.'/archmap-report.json',
        ];
    }

    /**
     * @param array<string, string> $outputs
     * @param array<string, string> $paths
     */
    private function writeOutputs(array $outputs, array $paths): void
    {
        foreach ($outputs as $name => $content) {
            if (!isset($paths[$name])) {
                continue;
            }
            $path = $paths[$name];
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $content);
        }
    }

    private function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));
        if (!in_array($format, ['mermaid', 'plantuml'], true)) {
            return 'mermaid';
        }

        return $format;
    }

    private function resolveRouteFilter(Graph $graph, ?string $routeFilter): string
    {
        $routeFilter = trim((string) $routeFilter);
        if ($routeFilter !== '') {
            return $routeFilter;
        }

        $routes = $graph->nodesByType('route');
        if ($routes === []) {
            return 'GET /';
        }

        return $routes[0]->name;
    }

    private function sequencePath(string $route, string $format): string
    {
        $diagramsPath = (string) $this->config->get('archmap.diagrams_path', base_path('docs/diagrams'));
        $sequenceDir = $diagramsPath.'/sequences';
        $ext = $format === 'plantuml' ? 'puml' : 'mmd';

        return $sequenceDir.'/'.$this->routeSlug($route).'.'.$ext;
    }

    private function routeSlug(string $route): string
    {
        $slug = strtolower(trim($route));
        $slug = str_replace(['|', ' ', '/'], '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? 'sequence';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'sequence';
    }

    private function cacheEnabled(): bool
    {
        return (bool) $this->config->get('archmap.cache.enabled', false);
    }

    private function cachePath(string $key): string
    {
        $root = (string) $this->config->get('archmap.cache.path', base_path('storage/framework/cache/archmap'));
        $safeKey = preg_replace('/[^a-z0-9\-_:.]+/i', '-', $key) ?? 'archmap';

        return rtrim($root, '/\\').DIRECTORY_SEPARATOR.$safeKey.'.json';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readCache(string $key, string $fingerprint): ?array
    {
        if (!$this->cacheEnabled()) {
            return null;
        }
        $payload = $this->cacheStore->get($this->cachePath($key));
        if ($payload === null) {
            return null;
        }
        if (($payload['fingerprint'] ?? '') !== $fingerprint) {
            return null;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, string> $outputs
     */
    private function writeCache(string $key, string $fingerprint, array $result, array $outputs): void
    {
        if (!$this->cacheEnabled()) {
            return;
        }
        $this->cacheStore->put($this->cachePath($key), [
            'fingerprint' => $fingerprint,
            'result' => $result,
            'outputs' => $outputs,
            'files' => $result['files'],
        ]);
    }

    /**
     * @param mixed $value
     * @return array<string, string>
     */
    private function toStringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $mapped = [];
        foreach ($value as $k => $v) {
            if (!is_string($k) || !is_string($v)) {
                continue;
            }
            $mapped[$k] = $v;
        }

        return $mapped;
    }

    /**
     * @param mixed $value
     * @return array<string, int>
     */
    private function toStats(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $stats = [];
        foreach ($value as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            if (is_int($v)) {
                $stats[$k] = $v;
            } elseif (is_numeric($v)) {
                $stats[$k] = (int) $v;
            }
        }

        return $stats;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function toStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $list = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * @param mixed $value
     * @return list<array<string, string>>
     */
    private function toIssues(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $issues = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = [];
            foreach ($item as $k => $v) {
                if (is_string($k) && is_string($v)) {
                    $normalized[$k] = $v;
                }
            }
            if ($normalized !== []) {
                $issues[] = $normalized;
            }
        }

        return $issues;
    }
}
