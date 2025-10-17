<?php

declare(strict_types=1);

namespace Agrovista\SaloonCircuitBreaker\States;

use Agrovista\SaloonCircuitBreaker\CircuitBreaker;
use Agrovista\SaloonCircuitBreaker\Contracts\CircuitBreakerStateInterface;
use Agrovista\SaloonCircuitBreaker\Enums\CircuitBreakerStateEnum;

class OpenState implements CircuitBreakerStateInterface
{
    public function canExecuteRequest(CircuitBreaker $breaker): bool
    {
        if ($breaker->hasExceededTimeout()) {
            $breaker->halfOpenCircuit();
            return true;
        }
        return false;
    }

    public function getStateName(): string
    {
        return $this->getState()->value;
    }

    public function getState(): CircuitBreakerStateEnum
    {
        return CircuitBreakerStateEnum::OPEN;
    }
}