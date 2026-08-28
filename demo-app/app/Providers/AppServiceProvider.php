<?php

namespace App\Providers;

use App\Models\ExpenseDispute;
use App\Models\ExpenseReport;
use App\Policies\ExpenseDisputePolicy;
use App\Policies\ExpenseReportPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ExpenseReport::class, ExpenseReportPolicy::class);
        Gate::policy(ExpenseDispute::class, ExpenseDisputePolicy::class);
    }
}
