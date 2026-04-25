<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('runs generate command end to end', function (): void {
    $base = sys_get_temp_dir().'/archmap-test-'.uniqid('', true);
    $models = $base.'/app/Models';
    $controllers = $base.'/app/Http/Controllers';
    $services = $base.'/app/Services';
    $docs = $base.'/docs';
    $diagrams = $docs.'/diagrams';

    foreach ([$models, $controllers, $services, $docs, $diagrams] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    file_put_contents($models.'/User.php', <<<'PHP'
<?php
namespace App\Models;

class User
{
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
PHP);

    file_put_contents($models.'/Order.php', <<<'PHP'
<?php
namespace App\Models;

class Order {}
PHP);

    file_put_contents($services.'/OrderService.php', <<<'PHP'
<?php
namespace App\Services;
class OrderService {}
PHP);

    file_put_contents($controllers.'/OrderController.php', <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Services\OrderService;

class OrderController
{
    public function __construct(private OrderService $service) {}
    public function index() {}
}
PHP);

    config()->set('archmap.paths.models', $models);
    config()->set('archmap.paths.controllers', $controllers);
    config()->set('archmap.paths.services', $services);
    config()->set('archmap.paths.repositories', $base.'/app/Repositories');
    config()->set('archmap.paths.jobs', $base.'/app/Jobs');
    config()->set('archmap.paths.events', $base.'/app/Events');
    config()->set('archmap.paths.listeners', $base.'/app/Listeners');
    config()->set('archmap.paths.policies', $base.'/app/Policies');
    config()->set('archmap.paths.requests', $base.'/app/Http/Requests');
    config()->set('archmap.paths.resources', $base.'/app/Http/Resources');
    config()->set('archmap.output_path', $docs);
    config()->set('archmap.diagrams_path', $diagrams);
    config()->set('archmap.cache.enabled', false);

    Route::get('/api/orders', [\App\Http\Controllers\OrderController::class, 'index'])->name('orders.index');

    $this->artisan('archmap:generate --format=mermaid')->assertExitCode(0);

    expect(file_exists($docs.'/architecture.md'))->toBeTrue();
    expect(file_exists($docs.'/archmap-report.json'))->toBeTrue();
    expect(file_exists($diagrams.'/routes.mmd'))->toBeTrue();
    expect(file_exists($diagrams.'/erd.mmd'))->toBeTrue();
    expect(file_exists($diagrams.'/classes.mmd'))->toBeTrue();
    expect(file_exists($diagrams.'/components.mmd'))->toBeTrue();
});
