<?php

namespace Ardana\Archmap\Commands;

use Ardana\Archmap\Services\ArchmapService;
use Illuminate\Console\Command;

final class ComponentsCommand extends Command
{
    protected $signature = 'archmap:components {--format=mermaid} {--dry-run} {--fresh}';
    protected $description = 'Generate component diagram.';

    public function handle(ArchmapService $service): int
    {
        $result = $service->generateComponents(
            !$this->option('dry-run'),
            $this->stringOption('format', 'mermaid'),
            (bool) $this->option('fresh')
        );
        $this->info('[archmap] Component diagram generated.');
        $this->line('File: '.$result['files']['components']);

        return self::SUCCESS;
    }

    private function stringOption(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}