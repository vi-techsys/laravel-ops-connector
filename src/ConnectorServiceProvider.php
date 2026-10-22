<?php

namespace LaravelOps\Connector;

use Illuminate\Support\ServiceProvider;
use LaravelOps\Connector\Http\Middleware\ValidateSignature;
use LaravelOps\Connector\Services\QueueMonitor;

class ConnectorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/connector.php', 'connector');

        // Register QueueMonitor as singleton for better performance
        $this->app->singleton(QueueMonitor::class, function ($app) {
            return new QueueMonitor();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/connector.php' => config_path('connector.php'),
        ], 'connector-config');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        // Register middleware
        $this->app['router']->aliasMiddleware('connector.auth', ValidateSignature::class);
    }
}
