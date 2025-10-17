<?php

namespace Tests\Support;

use Saloon\Http\Connector;
use AgrovistaUK\SaloonCircuitBreaker\CircuitBreakerPlugin;

class BasicTestConnector extends Connector
{
    use CircuitBreakerPlugin;

    public function resolveBaseUrl(): string
    {
        return 'https://test.example.com';
    }
}