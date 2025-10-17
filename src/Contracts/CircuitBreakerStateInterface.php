<?php

declare(strict_types=1);

namespace AgrovistaUK\SaloonCircuitBreaker\Contracts;

use AgrovistaUK\SaloonCircuitBreaker\CircuitBreaker;
use AgrovistaUK\SaloonCircuitBreaker\Enums\CircuitBreakerStateEnum;

interface CircuitBreakerStateInterface
{
    public function canExecuteRequest(CircuitBreaker $breaker): bool;
    public function getStateName(): string;
    public function getState(): CircuitBreakerStateEnum;
}