<?php

namespace Zerp\Timesheet\Providers;

use Illuminate\Support\ServiceProvider;

class TimesheetServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $routesPath = __DIR__.'/../Routes/web.php';
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }

        $apiRoutesPath = __DIR__.'/../Routes/api.php';
        if (file_exists($apiRoutesPath)) {
            $this->loadRoutesFrom($apiRoutesPath);
        }

        // Scoped Swagger/OpenAPI docs for this module at /docs/timesheet. Guarded
        // so the module still boots in a host app that has no Scramble.
        if (class_exists(\Dedoc\Scramble\Scramble::class)) {
            \Dedoc\Scramble\Scramble::registerApi('timesheet', [
                'api_path' => 'api/timesheet',
                'info' => ['version' => \Composer\InstalledVersions::getPrettyVersion('zerp/timesheet') ?? '1.0.0', 'description' => 'Zerp Timesheet module REST API for mobile and third-party clients.'],
                'ui' => ['title' => 'Zerp Timesheet API'],
            ])->expose(ui: '/docs/timesheet', document: '/docs/timesheet.json');
        }

        $migrationsPath = __DIR__.'/../Database/Migrations';
        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
    }
}