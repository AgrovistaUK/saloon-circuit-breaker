<?php

declare(strict_types=1);

namespace AgrovistaUK\SaloonCircuitBreaker\States;

use AgrovistaUK\SaloonCircuitBreaker\CircuitBreaker;
use AgrovistaUK\SaloonCircuitBreaker\Contracts\CircuitBreakerStateInterface;
use AgrovistaUK\SaloonCircuitBreaker\Enums\CircuitBreakerStateEnum;

class ClosedState implements CircuitBreakerStateInterface
{
    public function canExecuteRequest(CircuitBreaker $circuitBreaker): bool
    {
        return true;
    }

    public function handleSuccessfulRequest(CircuitBreaker $circuitBreaker): void
    {
        $circuitBreaker->recordResult(true);
    }

    public function handleFailedRequest(CircuitBreaker $circuitBreaker): void
    {
        $circuitBreaker->recordResult(false);
        if ($circuitBreaker->hasReachedFailureThreshold()) {
            $circuitBreaker->openCircuit();
        }
    }

    public function getStateName(): string
    {
        return $this->getState()->value;
    }

    public function getState(): CircuitBreakerStateEnum
    {
        return CircuitBreakerStateEnum::CLOSED;
    }
}