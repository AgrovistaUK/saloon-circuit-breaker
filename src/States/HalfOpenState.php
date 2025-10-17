<?php

declare(strict_types=1);

namespace Agrovista\SaloonCircuitBreaker\States;

use Agrovista\SaloonCircuitBreaker\CircuitBreaker;
use Agrovista\SaloonCircuitBreaker\Contracts\CircuitBreakerStateInterface;
use Agrovista\SaloonCircuitBreaker\Enums\CircuitBreakerStateEnum;

class HalfOpenState implements CircuitBreakerStateInterface
{
    public function canExecuteRequest(CircuitBreaker $breaker): bool
    {
        return $breaker->canAttemptHalfOpenRequest();
    }

    public function handleSuccessfulRequest(CircuitBreaker $breaker): void
    {
        $breaker->recordResult(true);
        if ($breaker->hasReachedSuccessThreshold()) {
            $breaker->closeCircuit();
        }
    }

    public function handleFailedRequest(CircuitBreaker $breaker): void
    {
        $breaker->recordResult(false);
        $breaker->openCircuit();
    }

    public function getStateName(): string
    {
        return $this->getState()->value;
    }

    public function getState(): CircuitBreakerStateEnum
    {
        return CircuitBreakerStateEnum::HALF_OPEN;
    }
}