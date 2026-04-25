<?php

namespace Ardana\Archmap\Scanners;

use Ardana\Archmap\Contracts\Scanner;
use Ardana\Archmap\Graph\Edge;
use Ardana\Archmap\Graph\Graph;
use Ardana\Archmap\Graph\Node;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

final class RouteScanner implements Scanner
{
    public function __construct(private readonly Router $router)
    {
    }

    public function scan(Graph $graph): array
    {
        $stats = ['routes' => 0];
        $warnings = [];

        /** @var list<Route> $routes */
        $routes = $this->router->getRoutes()->getRoutes();
        usort(
            $routes,
            static fn (Route $a, Route $b): int => [$a->uri(), implode('|', $a->methods())] <=> [$b->uri(), implode('|', $b->methods())]
        );

        foreach ($routes as $route) {
            $actionName = $route->getActionName();
            $methods = array_values(array_filter($route->methods(), fn (string $m): bool => $m !== 'HEAD'));
            $method = implode('|', $methods);
            $uri = '/'.ltrim($route->uri(), '/');

            $routeId = 'route:'.$method.' '.$uri;
            $graph->addNode(new Node(
                id: $routeId,
                type: 'route',
                name: $method.' '.$uri,
                metadata: [
                    'method' => $method,
                    'uri' => $uri,
                    'name' => $route->getName(),
                    'middleware' => $route->gatherMiddleware(),
                ]
            ));

            if ($actionName === 'Closure' || str_contains($actionName, 'Closure')) {
                $stats['routes']++;
                continue;
            }

            [$controller, $controllerMethod] = array_pad(explode('@', $actionName, 2), 2, 'handle');
            $controller = trim($controller, '\\');
            $controllerId = 'class:'.$controller;

            if (!$graph->hasNode($controllerId)) {
                $namespace = null;
                if (str_contains($controller, '\\')) {
                    $pos = strrpos($controller, '\\');
                    if ($pos !== false) {
                        $namespace = substr($controller, 0, $pos);
                    }
                }
                $graph->addNode(new Node(
                    id: $controllerId,
                    type: 'class',
                    name: class_basename($controller),
                    namespace: $namespace,
                    metadata: ['role' => 'controller']
                ));
            }

            $graph->addEdge(new Edge(
                from: $routeId,
                to: $controllerId,
                type: 'routes_to',
                label: $controllerMethod
            ));

            $stats['routes']++;
        }

        return ['stats' => $stats, 'warnings' => $warnings];
    }
}
