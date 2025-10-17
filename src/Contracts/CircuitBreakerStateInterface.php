<?php

declare(strict_types=1);

namespace Agrovista\SaloonCircuitBreaker\Contracts;

use Agrovista\SaloonCircuitBreaker\CircuitBreaker;
use Agrovista\SaloonCircuitBreaker\Enums\CircuitBreakerStateEnum;

interface CircuitBreakerStateInterface
{
    public function canExecuteRequest(CircuitBreaker $breaker): bool;
    public function getStateName(): string;
    public function getState(): CircuitBreakerStateEnum;
}