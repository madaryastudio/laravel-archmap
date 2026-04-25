<?php

namespace Ardana\Archmap\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;

final class CleanCommand extends Command
{
    protected $signature = 'archmap:clean';
    protected $description = 'Delete generated architecture files.';

    public function handle(Repository $config, Filesystem $files): int
    {
        $outputPath = (string) $config->get('archmap.output_path', base_path('docs'));
        $diagramsPath = (string) $config->get('archmap.diagrams_path', base_path('docs/diagrams'));

        if ($files->isDirectory($diagramsPath)) {
            $files->deleteDirectory($diagramsPath);
        }

        foreach (['architecture.md', 'archmap-report.json'] as $file) {
            $path = rtrim($outputPath, '/\\').DIRECTORY_SEPARATOR.$file;
            if ($files->exists($path)) {
                $files->delete($path);
            }
        }

        $this->info('[archmap] Generated files cleaned.');

        return self::SUCCESS;
    }
}
