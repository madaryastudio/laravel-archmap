<?php

namespace Ardana\Archmap\Graph;

final class Edge
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $type,
        public ?string $label = null,
        public array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'type' => $this->type,
            'label' => $this->label,
            'metadata' => $this->metadata,
        ];
    }
}
