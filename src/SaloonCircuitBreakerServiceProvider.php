<?php

declare(strict_types=1);

namespace AgrovistaUK\SaloonCircuitBreaker;

use Illuminate\Support\ServiceProvider;
use AgrovistaUK\SaloonCircuitBreaker\Services\CircuitBreakerRedisRegistry;
use AgrovistaUK\SaloonCircuitBreaker\Console\Commands\SaloonCircuitStatusCommand;

class SaloonCircuitBreakerServiceProvider extends ServiceProvider
{
    protected $defer = true;

    public function register(): void
    {
        $this->app->singleton(CircuitBreakerRedisRegistry::class, function ($app) {
            return new CircuitBreakerRedisRegistry();
        });
    }

    public function boot(): void
    {
        $this->commands([
            SaloonCircuitStatusCommand::class,
        ]);
    }

    public function provides(): array
    {
        return [CircuitBreakerRedisRegistry::class];
    }
}