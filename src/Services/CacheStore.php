<?php

namespace Ardana\Archmap\Services;

use Illuminate\Filesystem\Filesystem;

final class CacheStore
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $path): ?array
    {
        if (!$this->files->exists($path)) {
            return null;
        }

        $raw = $this->files->get($path);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function put(string $path, array $payload): void
    {
        $dir = dirname($path);
        $this->files->ensureDirectoryExists($dir);
        $this->files->put($path, (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
