<?php

declare(strict_types=1);

namespace AgrovistaUK\SaloonCircuitBreaker\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class CircuitBreakerMetricsData extends Data
{
    public function __construct(
        public int $successCount = 0,
        public int $failureCount = 0,
        public int $half_open_attempts = 0,
        public ?Carbon $lastFailureTime = null,
        public ?Carbon $lastSuccessTime = null,
        public ?Carbon $circuitOpenedAt = null,
    ) {}

    public function incrementSuccess(): self
    {
        return new self(
            successCount: $this->successCount + 1,
            failureCount: $this->failureCount,
            half_open_attempts: $this->half_open_attempts,
            lastFailureTime: $this->lastFailureTime,
            lastSuccessTime: Carbon::now(),
            circuitOpenedAt: $this->circuitOpenedAt,
        );
    }

    public function incrementFailure(): self
    {
        return new self(
            successCount: $this->successCount,
            failureCount: $this->failureCount + 1,
            half_open_attempts: $this->half_open_attempts,
            lastFailureTime: Carbon::now(),
            lastSuccessTime: $this->lastSuccessTime,
            circuitOpenedAt: $this->circuitOpenedAt,
        );
    }

    public function incrementHalfOpenRequestAttempts(): self
    {
        return new self(
            successCount: $this->successCount,
            failureCount: $this->failureCount,
            half_open_attempts: $this->half_open_attempts + 1,
            lastFailureTime: $this->lastFailureTime,
            lastSuccessTime: $this->lastSuccessTime,
            circuitOpenedAt: $this->circuitOpenedAt,
        );
    }

    public function reset(): self
    {
        return new self(
            successCount: 0,
            failureCount: 0,
            half_open_attempts: 0,
            lastFailureTime: null,
            lastSuccessTime: null,
            circuitOpenedAt: null,
        );
    }

    public function resetHalfOpenRequestAttempts(): self
    {
        return new self(
            successCount: $this->successCount,
            failureCount: $this->failureCount,
            half_open_attempts: 0,
            lastFailureTime: $this->lastFailureTime,
            lastSuccessTime: $this->lastSuccessTime,
            circuitOpenedAt: $this->circuitOpenedAt,
        );
    }

    public function markCircuitOpened(): self
    {
        return new self(
            successCount: $this->successCount,
            failureCount: $this->failureCount,
            half_open_attempts: $this->half_open_attempts,
            lastFailureTime: $this->lastFailureTime,
            lastSuccessTime: $this->lastSuccessTime,
            circuitOpenedAt: Carbon::now(),
        );
    }

    public function getTotalRequests(): int
    {
        return $this->successCount + $this->failureCount;
    }

    public function getSuccessRate(): float
    {
        $total = $this->getTotalRequests();
        if ($total === 0) {
            return 100.0;
        }
        return round(($this->successCount / $total) * 100, 2);
    }

    public function getFailureRate(): float
    {
        $total = $this->getTotalRequests();
        if ($total === 0) {
            return 0.0;
        }
        return round(($this->failureCount / $total) * 100, 2);
    }
}