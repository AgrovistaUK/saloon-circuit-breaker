<?php

namespace Tests\Support;

use Saloon\Http\Connector;
use Agrovista\SaloonCircuitBreaker\CircuitBreakerPlugin;

class TestApiConnector extends Connector
{
    use CircuitBreakerPlugin;

    public function resolveBaseUrl(): string
    {
        return 'https://api.example.com';
    }

    public function __construct()
    {
        $this->withCircuitBreaker([
            'service' => 'test_api',
            'failure_threshold' => 2,
            'success_threshold' => 2,
            'timeout' => 60,
            'half_open_attempts' => 3,
            'cache_prefix' => 'circuit_breaker:',
            'cache_ttl' => 3600,
        ]);
    }
}