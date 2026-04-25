<?php

namespace Ardana\Archmap\Commands;

use Ardana\Archmap\Services\ArchmapService;
use Illuminate\Console\Command;

final class ReportCommand extends Command
{
    protected $signature = 'archmap:report {--format=mermaid} {--dry-run} {--fresh}';
    protected $description = 'Generate architecture health report.';

    public function handle(ArchmapService $service): int
    {
        $result = $service->generate(
            ['routes', 'models', 'classes'],
            !$this->option('dry-run'),
            $this->stringOption('format', 'mermaid'),
            (bool) $this->option('fresh')
        );
        $this->info('[archmap] Architecture health report generated.');

        foreach ($result['issues'] as $issue) {
            $severity = strtoupper((string) ($issue['severity'] ?? 'INFO'));
            $message = (string) ($issue['message'] ?? '');

            if ($severity === 'WARNING' || $severity === 'CRITICAL') {
                $this->warn("{$severity}: {$message}");
                continue;
            }

            $this->line("{$severity}: {$message}");
        }

        return self::SUCCESS;
    }

    private function stringOption(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}