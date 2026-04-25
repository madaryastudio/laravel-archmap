<?php

namespace Ardana\Archmap\Services;

use Ardana\Archmap\Contracts\Renderer;
use Ardana\Archmap\Graph\Graph;
use Illuminate\Contracts\Container\Container;

final class RendererManager
{
    /**
     * @param array<string, class-string<Renderer>> $rendererMap
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $rendererMap,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $name, Graph $graph, array $context = []): string
    {
        /** @var Renderer $renderer */
        $renderer = $this->container->make($this->rendererMap[$name]);

        return $renderer->render($graph, $context);
    }
}
