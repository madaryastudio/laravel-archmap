<?php

namespace Ardana\Archmap\Commands;

use Ardana\Archmap\Services\ArchmapService;
use Illuminate\Console\Command;

final class SequenceCommand extends Command
{
    protected $signature = 'archmap:sequence {--route=} {--format=mermaid} {--dry-run} {--fresh}';
    protected $description = 'Generate sequence diagram for a route.';

    public function handle(ArchmapService $service): int
    {
        $route = $this->nullableStringOption('route');
        $result = $service->generateSequence(
            routeFilter: $route,
            write: !$this->option('dry-run'),
            format: $this->stringOption('format', 'mermaid'),
            fresh: (bool) $this->option('fresh')
        );
        $this->info('[archmap] Sequence diagram generated.');
        $this->line('Route: '.$result['route']);
        $this->line('File: '.$result['files']['sequence']);

        return self::SUCCESS;
    }

    private function stringOption(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function nullableStringOption(string $name): ?string
    {
        $value = $this->option($name);
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
