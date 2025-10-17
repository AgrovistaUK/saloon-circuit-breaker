<?php

declare(strict_types=1);

namespace Agrovista\SaloonCircuitBreaker;

use Illuminate\Support\ServiceProvider;
use Agrovista\SaloonCircuitBreaker\Services\CircuitBreakerRedisRegistry;
use Agrovista\SaloonCircuitBreaker\Console\Commands\SaloonCircuitStatusCommand;

class SaloonCircuitBreakerServiceProvider extends ServiceProvider
{
    public function register(): void{
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
}