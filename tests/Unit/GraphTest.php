<?php

declare(strict_types=1);

use Ardana\Archmap\Graph\Edge;
use Ardana\Archmap\Graph\Graph;
use Ardana\Archmap\Graph\Node;

it('stores nodes and edges deterministically', function (): void {
    $graph = new Graph();
    $graph->addNode(new Node('class:App\\Services\\BService', 'class', 'BService'));
    $graph->addNode(new Node('class:App\\Services\\AService', 'class', 'AService'));
    $graph->addEdge(new Edge('class:App\\Services\\BService', 'class:App\\Services\\AService', 'depends_on'));
    $graph->addEdge(new Edge('class:App\\Services\\AService', 'class:App\\Services\\BService', 'depends_on'));

    expect($graph->nodes()[0]->name)->toBe('AService');
    expect($graph->edges()[0]->from)->toBe('class:App\\Services\\AService');
});
