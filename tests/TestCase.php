<?php

declare(strict_types=1);

namespace Ardana\Archmap\Tests;

use Ardana\Archmap\ArchmapServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ArchmapServiceProvider::class];
    }
}
