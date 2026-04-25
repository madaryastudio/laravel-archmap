<?php

namespace Ardana\Archmap\Commands;

use Ardana\Archmap\Services\ArchmapService;
use Illuminate\Console\Command;

final class CiCommand extends Command
{
    protected $signature = 'archmap:ci {--fail-on=critical} {--format=mermaid} {--dry-run} {--fresh}';
    protected $description = 'Run architecture checks for CI pipeline.';

    public function handle(ArchmapService $service): int
    {
        $failOn = strtolower($this->stringOption('fail-on', 'critical'));
        $result = $service->generate(
            ['routes', 'models', 'classes'],
            !$this->option('dry-run'),
            $this->stringOption('format', 'mermaid'),
            (bool) $this->option('fresh')
        );
        $hasWarning = false;
        $hasCritical = false;

        foreach ($result['issues'] as $issue) {
            $severity = strtolower((string) ($issue['severity'] ?? 'info'));
            if ($severity === 'warning') {
                $hasWarning = true;
            }
            if ($severity === 'critical') {
                $hasCritical = true;
            }
            $this->line(strtoupper($severity).': '.(string) ($issue['message'] ?? ''));
        }

        if ($failOn === 'warning' && ($hasWarning || $hasCritical)) {
            return self::FAILURE;
        }

        if ($failOn === 'critical' && $hasCritical) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function stringOption(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
