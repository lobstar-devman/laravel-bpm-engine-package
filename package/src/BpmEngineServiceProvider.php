<?php

namespace Lobstar\BpmEngine;

use Illuminate\Support\ServiceProvider;
use Lobstar\BpmEngine\Core\EventStore;
use Lobstar\BpmEngine\Core\ModelRegistry;
use Lobstar\BpmEngine\Core\QueueDispatcher;
use Lobstar\BpmEngine\Core\RevisionManager;

class BpmEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModelRegistry::class);
        $this->app->singleton(EventStore::class);
        $this->app->singleton(QueueDispatcher::class);
        $this->app->singleton(RevisionManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'bpm-engine-migrations');
        }
    }
}
