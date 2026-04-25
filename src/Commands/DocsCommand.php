<?php

namespace Ardana\Archmap\Commands;

use Ardana\Archmap\Services\ArchmapService;
use Illuminate\Console\Command;

final class DocsCommand extends Command
{
    protected $signature = 'archmap:docs {--format=mermaid} {--dry-run} {--fresh}';
    protected $description = 'Generate architecture markdown document.';

    public function handle(ArchmapService $service): int
    {
        $result = $service->generate(
            ['routes', 'models', 'classes'],
            !$this->option('dry-run'),
            $this->stringOption('format', 'mermaid'),
            (bool) $this->option('fresh')
        );
        $this->info('[archmap] Architecture markdown generated.');
        $this->line('File: '.$result['files']['architecture']);

        return self::SUCCESS;
    }

    private function stringOption(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}