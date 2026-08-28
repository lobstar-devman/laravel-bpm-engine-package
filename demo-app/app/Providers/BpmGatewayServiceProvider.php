<?php

namespace App\Providers;

use App\Bpm\Adapters\PackageBulkTransitionGateway;
use App\Bpm\Adapters\PackageDecisionGateway;
use App\Bpm\Adapters\PackageModelDefinitionGateway;
use App\Bpm\Adapters\PackageRevisionGateway;
use App\Bpm\Contracts\BulkTransitionGateway;
use App\Bpm\Contracts\DecisionGateway;
use App\Bpm\Contracts\ModelDefinitionGateway;
use App\Bpm\Contracts\RevisionGateway;
use Illuminate\Support\ServiceProvider;

class BpmGatewayServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(RevisionGateway::class, PackageRevisionGateway::class);
        $this->app->singleton(BulkTransitionGateway::class, PackageBulkTransitionGateway::class);
        $this->app->singleton(DecisionGateway::class, PackageDecisionGateway::class);
        $this->app->singleton(ModelDefinitionGateway::class, PackageModelDefinitionGateway::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
