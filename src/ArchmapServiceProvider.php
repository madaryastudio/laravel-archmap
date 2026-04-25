<?php

namespace Ardana\Archmap;

use Ardana\Archmap\Analyzers\HealthAnalyzer;
use Ardana\Archmap\Commands\CiCommand;
use Ardana\Archmap\Commands\ClassesCommand;
use Ardana\Archmap\Commands\CleanCommand;
use Ardana\Archmap\Commands\ComponentsCommand;
use Ardana\Archmap\Commands\DocsCommand;
use Ardana\Archmap\Commands\ErdCommand;
use Ardana\Archmap\Commands\GenerateCommand;
use Ardana\Archmap\Commands\ReportCommand;
use Ardana\Archmap\Commands\RoutesCommand;
use Ardana\Archmap\Commands\SequenceCommand;
use Ardana\Archmap\Renderers\JsonRenderer;
use Ardana\Archmap\Renderers\MarkdownRenderer;
use Ardana\Archmap\Renderers\MermaidRenderer;
use Ardana\Archmap\Renderers\PlantUmlRenderer;
use Ardana\Archmap\Scanners\ClassScanner;
use Ardana\Archmap\Scanners\ModelScanner;
use Ardana\Archmap\Scanners\RouteScanner;
use Ardana\Archmap\Services\ArchmapService;
use Ardana\Archmap\Services\CacheStore;
use Ardana\Archmap\Services\RendererManager;
use Ardana\Archmap\Services\ScannerManager;
use Ardana\Archmap\Support\FileFinder;
use Ardana\Archmap\Support\PhpFileParser;
use Illuminate\Support\ServiceProvider;

final class ArchmapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/archmap.php', 'archmap');

        $this->app->singleton(FileFinder::class);
        $this->app->singleton(PhpFileParser::class);
        $this->app->singleton(HealthAnalyzer::class);
        $this->app->singleton(CacheStore::class);

        $this->app->singleton(ScannerManager::class, function ($app): ScannerManager {
            return new ScannerManager($app, [
                'routes' => RouteScanner::class,
                'models' => ModelScanner::class,
                'classes' => ClassScanner::class,
            ]);
        });

        $this->app->singleton(RendererManager::class, function ($app): RendererManager {
            return new RendererManager($app, [
                'mermaid' => MermaidRenderer::class,
                'plantuml' => PlantUmlRenderer::class,
                'markdown' => MarkdownRenderer::class,
                'json' => JsonRenderer::class,
            ]);
        });

        $this->app->singleton(ArchmapService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/archmap.php' => config_path('archmap.php'),
        ], 'archmap-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateCommand::class,
                RoutesCommand::class,
                ErdCommand::class,
                ClassesCommand::class,
                DocsCommand::class,
                ReportCommand::class,
                CiCommand::class,
                CleanCommand::class,
                ComponentsCommand::class,
                SequenceCommand::class,
            ]);
        }
    }
}
