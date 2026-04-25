<?php

declare(strict_types=1);

function assertMatchesSnapshot(string $name, string $actual): void
{
    $snapshotPath = __DIR__.'/../Snapshots/'.$name.'.snap';
    $normalized = str_replace("\r\n", "\n", $actual);

    if (!file_exists($snapshotPath) || getenv('UPDATE_SNAPSHOTS') === '1') {
        file_put_contents($snapshotPath, $normalized);
    }

    $expected = (string) file_get_contents($snapshotPath);
    expect($normalized)->toBe($expected);
}
