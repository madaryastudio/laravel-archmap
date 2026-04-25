<?php

namespace Ardana\Archmap\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FileFinder
{
    /**
     * @param list<string> $ignorePaths
     * @return list<string>
     */
    public function phpFiles(string $path, array $ignorePaths = []): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $ignore = array_map([$this, 'normalize'], $ignorePaths);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        $files = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getPathname();
            $normalized = $this->normalize($realPath);

            if ($this->isIgnored($normalized, $ignore)) {
                continue;
            }

            $files[] = $realPath;
        }

        sort($files);

        return $files;
    }

    /**
     * @param list<string> $ignore
     */
    private function isIgnored(string $path, array $ignore): bool
    {
        foreach ($ignore as $candidate) {
            if ($candidate !== '' && str_starts_with($path, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', rtrim($path, '/\\'));
    }
}
