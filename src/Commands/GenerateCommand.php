<?php

namespace Ardana\Archmap\Commands;

use Ardana\Archmap\Services\ArchmapService;
use Illuminate\Console\Command;

final class GenerateCommand extends Command
{
    protected $signature = 'archmap:generate {--format=mermaid} {--dry-run} {--fresh}';
    protected $description = 'Generate all architecture documentation files.';

    public function handle(ArchmapService $service): int
    {
        $format = $this->stringOption('format', 'mermaid');
        $result = $service->generate(
            ['routes', 'models', 'classes'],
            !$this->option('dry-run'),
            $format,
            (bool) $this->option('fresh')
        );
        $this->line('[archmap] Generated documentation.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Routes', (string) ($result['stats']['routes'] ?? 0)],
                ['Models', (string) ($result['stats']['models'] ?? 0)],
                ['Classes', (string) ($result['stats']['classes'] ?? 0)],
                ['Warnings', (string) count($result['warnings'])],
                ['Format', $result['format']],
                ['Cache', $result['from_cache'] ? 'hit' : 'miss'],
            ]
        );

        foreach ($result['warnings'] as $warning) {
            $this->warn('[archmap] '.$warning);
        }

        return self::SUCCESS;
    }

    private function stringOption(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
