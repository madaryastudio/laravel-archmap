<?php

namespace Ardana\Archmap\Graph;

final class Node
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public ?string $namespace = null,
        public ?string $path = null,
        public array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'namespace' => $this->namespace,
            'path' => $this->path,
            'metadata' => $this->metadata,
        ];
    }
}
