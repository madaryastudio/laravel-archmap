<?php

namespace Ardana\Archmap\Commands;

use Ardana\Archmap\Services\ArchmapService;
use Illuminate\Console\Command;

final class ClassesCommand extends Command
{
    protected $signature = 'archmap:classes {--format=mermaid} {--dry-run} {--fresh}';
    protected $description = 'Generate class diagram.';

    public function handle(ArchmapService $service): int
    {
        $result = $service->generate(
            ['classes'],
            !$this->option('dry-run'),
            $this->stringOption('format', 'mermaid'),
            (bool) $this->option('fresh')
        );
        $this->info('[archmap] Class diagram generated.');
        $this->line('Classes: '.($result['stats']['classes'] ?? 0));

        return self::SUCCESS;
    }

    private function stringOption(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}