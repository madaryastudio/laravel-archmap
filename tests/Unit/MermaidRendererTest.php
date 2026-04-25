<?php

declare(strict_types=1);

use Ardana\Archmap\Graph\Edge;
use Ardana\Archmap\Graph\Graph;
use Ardana\Archmap\Graph\Node;
use Ardana\Archmap\Renderers\MermaidRenderer;

it('renders routes, components, and sequence diagrams', function (): void {
    $graph = new Graph();
    $graph->addNode(new Node('route:GET /api/orders', 'route', 'GET /api/orders'));
    $graph->addNode(new Node(
        'class:App\\Http\\Controllers\\OrderController',
        'class',
        'OrderController',
        'App\\Http\\Controllers',
        null,
        [
            'role' => 'controllers',
            'constructor_dependencies' => ['App\\Services\\OrderService'],
        ]
    ));
    $graph->addNode(new Node(
        'class:App\\Services\\OrderService',
        'class',
        'OrderService',
        'App\\Services',
        null,
        ['role' => 'services']
    ));
    $graph->addEdge(new Edge(
        'route:GET /api/orders',
        'class:App\\Http\\Controllers\\OrderController',
        'routes_to',
        'index'
    ));
    $graph->addEdge(new Edge(
        'class:App\\Http\\Controllers\\OrderController',
        'class:App\\Services\\OrderService',
        'depends_on'
    ));

    $renderer = new MermaidRenderer();

    $routes = $renderer->render($graph, ['diagram' => 'routes']);
    $components = $renderer->render($graph, ['diagram' => 'components']);
    $sequence = $renderer->render($graph, ['diagram' => 'sequence', 'route' => 'GET /api/orders']);

    expect($routes)->toContain('flowchart TD');
    expect($components)->toContain('subgraph Controllers');
    expect($sequence)->toContain('sequenceDiagram');
    expect($sequence)->toContain('confidence');

    assertMatchesSnapshot('mermaid-routes', $routes);
    assertMatchesSnapshot('mermaid-components', $components);
    assertMatchesSnapshot('mermaid-sequence', $sequence);
});
