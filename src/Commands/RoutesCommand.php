<?php

namespace Ardana\Archmap\Commands;

use Ardana\Archmap\Services\ArchmapService;
use Illuminate\Console\Command;

final class RoutesCommand extends Command
{
    protected $signature = 'archmap:routes {--format=mermaid} {--dry-run} {--fresh}';
    protected $description = 'Generate route map diagram.';

    public function handle(ArchmapService $service): int
    {
        $result = $service->generate(
            ['routes'],
            !$this->option('dry-run'),
            $this->stringOption('format', 'mermaid'),
            (bool) $this->option('fresh')
        );
        $this->info('[archmap] Route map generated.');
        $this->line('Routes: '.($result['stats']['routes'] ?? 0));

        return self::SUCCESS;
    }

    private function stringOption(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}