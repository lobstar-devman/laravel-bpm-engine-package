<?php

namespace Lobstar\BpmEngine;

use Illuminate\Support\ServiceProvider;
use Lobstar\BpmEngine\Bpmn\BpmnInterpreter;
use Lobstar\BpmEngine\Bpmn\BpmnParser;
use Lobstar\BpmEngine\Cmmn\CmmnInterpreter;
use Lobstar\BpmEngine\Cmmn\CmmnParser;
use Lobstar\BpmEngine\Core\EventStore;
use Lobstar\BpmEngine\Core\ModelRegistry;
use Lobstar\BpmEngine\Core\QueueDispatcher;
use Lobstar\BpmEngine\Core\RevisionManager;
use Lobstar\BpmEngine\Dmn\DmnEvaluator;
use Lobstar\BpmEngine\Dmn\DmnParser;

class BpmEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BpmnParser::class);
        $this->app->singleton(BpmnInterpreter::class);
        $this->app->singleton(CmmnParser::class);
        $this->app->singleton(CmmnInterpreter::class);
        $this->app->singleton(DmnParser::class);
        $this->app->singleton(DmnEvaluator::class);

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
