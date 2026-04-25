<?php

namespace Ardana\Archmap\Contracts;

use Ardana\Archmap\Graph\Graph;

interface Scanner
{
    /**
     * @return array{stats: array<string, int>, warnings: list<string>}
     */
    public function scan(Graph $graph): array;
}
