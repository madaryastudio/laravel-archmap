<?php

namespace Ardana\Archmap\Commands;

use Ardana\Archmap\Services\ArchmapService;
use Illuminate\Console\Command;

final class ErdCommand extends Command
{
    protected $signature = 'archmap:erd {--format=mermaid} {--dry-run} {--fresh}';
    protected $description = 'Generate ERD diagram.';

    public function handle(ArchmapService $service): int
    {
        $result = $service->generate(
            ['models'],
            !$this->option('dry-run'),
            $this->stringOption('format', 'mermaid'),
            (bool) $this->option('fresh')
        );
        $this->info('[archmap] ERD generated.');
        $this->line('Models: '.($result['stats']['models'] ?? 0));
        $this->line('Relationships: '.($result['stats']['relationships'] ?? 0));

        return self::SUCCESS;
    }

    private function stringOption(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}