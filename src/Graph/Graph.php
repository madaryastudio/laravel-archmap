<?php

namespace Ardana\Archmap\Graph;

final class Graph
{
    /** @var array<string, Node> */
    private array $nodes = [];

    /** @var list<Edge> */
    private array $edges = [];

    public function addNode(Node $node): void
    {
        $this->nodes[$node->id] = $node;
    }

    public function hasNode(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    public function addEdge(Edge $edge): void
    {
        $this->edges[] = $edge;
    }

    /**
     * @return list<Node>
     */
    public function nodes(): array
    {
        $nodes = array_values($this->nodes);
        usort($nodes, fn (Node $a, Node $b): int => strcmp($a->id, $b->id));

        return $nodes;
    }

    /**
     * @return list<Edge>
     */
    public function edges(): array
    {
        $edges = $this->edges;
        usort(
            $edges,
            static function (Edge $a, Edge $b): int {
                return [$a->from, $a->to, $a->type, $a->label ?? '']
                    <=> [$b->from, $b->to, $b->type, $b->label ?? ''];
            }
        );

        return $edges;
    }

    /**
     * @return list<Node>
     */
    public function nodesByType(string $type): array
    {
        return array_values(array_filter($this->nodes(), fn (Node $n): bool => $n->type === $type));
    }

    /**
     * @return list<Edge>
     */
    public function edgesByType(string $type): array
    {
        return array_values(array_filter($this->edges(), fn (Edge $e): bool => $e->type === $type));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nodes' => array_map(fn (Node $n): array => $n->toArray(), $this->nodes()),
            'edges' => array_map(fn (Edge $e): array => $e->toArray(), $this->edges()),
        ];
    }
}
