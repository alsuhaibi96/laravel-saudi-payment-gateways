<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class SaudiPaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/saudi-payments.php', 'saudi-payments');

        $this->app->singleton(GatewayFactory::class);
        $this->app->singleton(PaymentManager::class);
        $this->app->bind(WebhookProcessor::class, fn ($app) => new WebhookProcessor($app['db']->connection(), $app['events']));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (config('saudi-payments.routes.enabled', true)) {
            Route::group([
                'prefix' => config('saudi-payments.routes.prefix', ''),
                'middleware' => config('saudi-payments.routes.middleware', ['api']),
            ], function (): void {
                $this->loadRoutesFrom(__DIR__.'/../routes/payments.php');
            });
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/saudi-payments.php' => config_path('saudi-payments.php'),
            ], 'saudi-payments-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'saudi-payments-migrations');
        }
    }
}
