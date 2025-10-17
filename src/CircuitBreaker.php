<?php

declare(strict_types=1);

namespace AgrovistaUK\SaloonCircuitBreaker;

use Throwable;
use Carbon\Carbon;
use RuntimeException;
use Illuminate\Support\Facades\Cache;
use AgrovistaUK\SaloonCircuitBreaker\States\OpenState;
use AgrovistaUK\SaloonCircuitBreaker\States\ClosedState;
use AgrovistaUK\SaloonCircuitBreaker\States\HalfOpenState;
use AgrovistaUK\SaloonCircuitBreaker\Data\CircuitBreakerConfigData;
use AgrovistaUK\SaloonCircuitBreaker\Data\CircuitBreakerStatusData;
use AgrovistaUK\SaloonCircuitBreaker\Enums\CircuitBreakerCacheEnum;
use AgrovistaUK\SaloonCircuitBreaker\Enums\CircuitBreakerStateEnum;
use AgrovistaUK\SaloonCircuitBreaker\Data\CircuitBreakerMetricsData;
use AgrovistaUK\SaloonCircuitBreaker\Exceptions\CircuitOpenException;
use AgrovistaUK\SaloonCircuitBreaker\Services\CircuitBreakerRedisRegistry;
use AgrovistaUK\SaloonCircuitBreaker\Contracts\CircuitBreakerStateInterface;

class CircuitBreaker
{
    protected CircuitBreakerStateInterface $circuitBreakerStateInterface;
    protected CircuitBreakerMetricsData $circuitBreakerMetricsData;

    public function __construct(protected string $service, protected CircuitBreakerConfigData $circuitBreakerConfigData, protected CircuitBreakerRedisRegistry $redisRegistry) {
        $this->registerService();
        $this->loadStateFromCache();
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function getCurrentState(): CircuitBreakerStateEnum
    {
        return $this->circuitBreakerStateInterface->getState();
    }

    public function canExecuteRequest(): bool
    {
        return $this->circuitBreakerStateInterface->canExecuteRequest($this);
    }

    public function recordResult(bool $success): void
    {
        $this->withLock(function () use ($success) {
            $this->circuitBreakerMetricsData = $success
                ? $this->circuitBreakerMetricsData->incrementSuccess()
                : $this->circuitBreakerMetricsData->incrementFailure();
            $this->saveStateToCache();
        });
    }

    public function hasReachedFailureThreshold(): bool
    {
        return $this->circuitBreakerMetricsData->failureCount >= $this->circuitBreakerConfigData->failure_threshold;
    }

    public function hasReachedSuccessThreshold(): bool
    {
        return $this->circuitBreakerMetricsData->successCount >= $this->circuitBreakerConfigData->success_threshold;
    }

    public function hasExceededTimeout(): bool
    {
        if (!$this->circuitBreakerMetricsData->circuitOpenedAt) {
            return false;
        }
        return $this->circuitBreakerMetricsData->circuitOpenedAt->addSeconds($this->circuitBreakerConfigData->timeout)->isPast();
    }

    public function canAttemptHalfOpenRequest(): bool
    {
        return $this->circuitBreakerMetricsData->half_open_attempts < $this->circuitBreakerConfigData->half_open_attempts;
    }

    public function openCircuit(): void
    {
        $this->withLock(function () {
            $this->circuitBreakerStateInterface = new OpenState();
            $this->circuitBreakerMetricsData = $this->circuitBreakerMetricsData->markCircuitOpened();
            $this->saveStateToCache();
        });
    }

    public function closeCircuit(): void
    {
        $this->withLock(function () {
            $this->circuitBreakerStateInterface = new ClosedState();
            $this->circuitBreakerMetricsData = $this->circuitBreakerMetricsData->reset();
            $this->saveStateToCache();
        });
    }

    public function halfOpenCircuit(): void
    {
        $this->withLock(function () {
            $this->circuitBreakerStateInterface = new HalfOpenState();
            $this->circuitBreakerMetricsData = $this->circuitBreakerMetricsData->resetHalfOpenRequestAttempts();
            $this->saveStateToCache();
        });
    }

    public function executeRequest(callable $request): mixed
    {
        if (!$this->canExecuteRequest()) {
            throw new CircuitOpenException(
                service: $this->service,
                nextRetryAt: $this->getNextRetryAt(),
                status: $this->getStatus()
            );
        }
        if ($this->circuitBreakerStateInterface->getState() === CircuitBreakerStateEnum::HALF_OPEN) {
            $this->circuitBreakerMetricsData = $this->circuitBreakerMetricsData->incrementHalfOpenRequestAttempts();
            $this->saveStateToCache();
        }
        try {
            if ($this->circuitBreakerStateInterface instanceof ClosedState || $this->circuitBreakerStateInterface instanceof HalfOpenState) {
                $this->circuitBreakerStateInterface->handleSuccessfulRequest($this);
            }
            return $request();
        } catch (Throwable $exception) {
            if ($this->circuitBreakerStateInterface instanceof ClosedState || $this->circuitBreakerStateInterface instanceof HalfOpenState) {
                $this->circuitBreakerStateInterface->handleFailedRequest($this);
            }
            throw $exception;
        }
    }

    public function getStatus(): CircuitBreakerStatusData
    {
        return new CircuitBreakerStatusData(
            service: $this->service,
            state: $this->circuitBreakerStateInterface->getStateName(),
            metrics: $this->circuitBreakerMetricsData,
            config: $this->circuitBreakerConfigData,
            timestamp: Carbon::now(),
        );
    }

    public function getNextRetryAt(): ?Carbon
    {
        if ($this->circuitBreakerStateInterface->getState() !== CircuitBreakerStateEnum::OPEN || !$this->circuitBreakerMetricsData->circuitOpenedAt) {
            return null;
        }
        return $this->circuitBreakerMetricsData->circuitOpenedAt->addSeconds($this->circuitBreakerConfigData->timeout);
    }

    private function loadStateFromCache(): void
    {
        $stateData = Cache::get($this->getStateKey(), []);
        $metricsData = Cache::get($this->getMetricsKey(), []);
        $this->circuitBreakerMetricsData = CircuitBreakerMetricsData::from($metricsData ?: []);
        $stateName = $stateData['state'] ?? CircuitBreakerStateEnum::CLOSED->value;
        $this->circuitBreakerStateInterface = match ($stateName) {
            CircuitBreakerStateEnum::OPEN->value => new OpenState(),
            CircuitBreakerStateEnum::HALF_OPEN->value => new HalfOpenState(),
            default => new ClosedState(),
        };
    }

    private function saveStateToCache(): void
    {
        $ttl = Carbon::now()->addSeconds($this->circuitBreakerConfigData->cache_ttl);
        Cache::put($this->getStateKey(), ['state' => $this->circuitBreakerStateInterface->getStateName(), 'updated_at' => Carbon::now()->toISOString()], $ttl);
        Cache::put($this->getMetricsKey(), $this->circuitBreakerMetricsData->toArray(), $ttl);
    }

    private function getStateKey(): string
    {
        return $this->circuitBreakerConfigData->cache_prefix . $this->service . CircuitBreakerCacheEnum::STATE_SUFFIX->value;
    }

    private function getMetricsKey(): string
    {
        return $this->circuitBreakerConfigData->cache_prefix . $this->service . CircuitBreakerCacheEnum::METRICS_SUFFIX->value;
    }

    private function getLockKey(): string
    {
        return $this->circuitBreakerConfigData->cache_prefix . $this->service . CircuitBreakerCacheEnum::LOCK_SUFFIX->value;
    }

    private function withLock(callable $callback): void
    {
        $lock = Cache::lock($this->getLockKey(), 10);
        throw_if(!$lock->get(), RuntimeException::class, 'Failed to get the lock for circuit breaker: ' . $this->service);
        try {
            $this->loadStateFromCache();
            $callback();
        } finally {
            $lock->release();
        }
    }

    private function registerService(): void
    {
        $this->redisRegistry->registerService($this->service);
    }
}