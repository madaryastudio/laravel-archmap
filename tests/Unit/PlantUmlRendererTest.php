<?php

declare(strict_types=1);

use Ardana\Archmap\Graph\Edge;
use Ardana\Archmap\Graph\Graph;
use Ardana\Archmap\Graph\Node;
use Ardana\Archmap\Renderers\PlantUmlRenderer;

it('renders plantuml diagrams', function (): void {
    $graph = new Graph();
    $graph->addNode(new Node('route:GET /api/orders', 'route', 'GET /api/orders'));
    $graph->addNode(new Node('model:App\\Models\\Order', 'model', 'Order', 'App\\Models'));
    $graph->addNode(new Node('model:App\\Models\\User', 'model', 'User', 'App\\Models'));
    $graph->addNode(new Node('class:App\\Http\\Controllers\\OrderController', 'class', 'OrderController', 'App\\Http\\Controllers'));
    $graph->addEdge(new Edge('route:GET /api/orders', 'class:App\\Http\\Controllers\\OrderController', 'routes_to', 'index'));
    $graph->addEdge(new Edge('model:App\\Models\\User', 'model:App\\Models\\Order', 'hasMany', 'orders'));

    $renderer = new PlantUmlRenderer();
    $classes = $renderer->render($graph, ['diagram' => 'classes']);
    $erd = $renderer->render($graph, ['diagram' => 'erd']);

    expect($classes)->toContain('@startuml');
    expect($classes)->toContain('@enduml');
    expect($erd)->toContain('||--o{');
});
