<?php

namespace Ardana\Archmap\Contracts;

use Ardana\Archmap\Graph\Graph;

interface Renderer
{
    /**
     * @param array<string, mixed> $context
     */
    public function render(Graph $graph, array $context = []): string;
}
