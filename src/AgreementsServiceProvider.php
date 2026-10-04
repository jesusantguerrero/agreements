<?php

namespace Insane\Agreements;

use Illuminate\Support\ServiceProvider;
use Insane\Agreements\Console\GenerateContractInvoices;

class AgreementsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/agreements.php', 'agreements');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/agreements.php' => config_path('agreements.php'),
        ], 'agreements-config');

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateContractInvoices::class]);
        }
    }
}
