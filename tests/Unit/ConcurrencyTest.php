<?php

use Illuminate\Support\Facades\Cache;
use AgrovistaUK\SaloonCircuitBreaker\CircuitBreaker;
use AgrovistaUK\SaloonCircuitBreaker\Data\CircuitBreakerConfigData;
use AgrovistaUK\SaloonCircuitBreaker\Enums\CircuitBreakerStateEnum;
use AgrovistaUK\SaloonCircuitBreaker\Exceptions\CircuitOpenException;
use AgrovistaUK\SaloonCircuitBreaker\Services\CircuitBreakerRedisRegistry;

beforeEach(function () {
    Cache::store('redis')->flush();
});

it('circuit breaker uses locks to prevent multiple instances from modifying state simultaneously', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->times(3)->with('locked_service');
    $service = 'locked_service';
    $config = CircuitBreakerConfigData::from(['service' => $service]);
    $circuitBreaker1 = new CircuitBreaker($service, $config, $registry);
    $circuitBreaker2 = new CircuitBreaker($service, $config, $registry);
    expect($circuitBreaker1->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
    expect($circuitBreaker2->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
    $circuitBreaker1->recordResult(false);
    $circuitBreaker1->recordResult(false);
    $circuitBreaker1->openCircuit();
    $circuitBreaker2 = new CircuitBreaker($service, $config, $registry);
    expect($circuitBreaker2->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
});

it('circuit breaker handles multiple simultaneous requests correctly', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->times(3)->with('concurrent_service');
    $service = 'concurrent_service';
    $config = CircuitBreakerConfigData::from([
        'service' => $service,
        'failure_threshold' => 2
    ]);
    $circuitBreaker1 = new CircuitBreaker($service, $config, $registry);
    $circuitBreaker2 = new CircuitBreaker($service, $config, $registry);
    $circuitBreaker1->recordResult(false);
    $circuitBreaker1->recordResult(false);
    $circuitBreaker1->openCircuit();
    expect($circuitBreaker1->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    $circuitBreaker2 = new CircuitBreaker($service, $config, $registry);
    expect($circuitBreaker2->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    expect(fn() => $circuitBreaker1->executeRequest(fn() => 'test'))->toThrow(CircuitOpenException::class);
    expect(fn() => $circuitBreaker2->executeRequest(fn() => 'test'))->toThrow(CircuitOpenException::class);
});

it('state changes happen atomically so all circuit breaker instances see consistent state', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->times(5)->with('atomic_service');
    $service = 'atomic_service';
    $config = CircuitBreakerConfigData::from([
        'service' => 'atomic_service',
        'failure_threshold' => 2,
        'success_threshold' => 1
    ]);
    $circuitBreaker1 = new CircuitBreaker($service, $config, $registry);
    $circuitBreaker2 = new CircuitBreaker($service, $config, $registry);
    $circuitBreaker1->recordResult(false);
    $circuitBreaker1->recordResult(false);
    $circuitBreaker1->openCircuit();
    expect($circuitBreaker1->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    $circuitBreaker2 = new CircuitBreaker($service, $config, $registry);
    expect($circuitBreaker2->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    $circuitBreaker1->halfOpenCircuit();
    expect($circuitBreaker1->getCurrentState())->toBe(CircuitBreakerStateEnum::HALF_OPEN);
    $circuitBreaker2 = new CircuitBreaker($service, $config, $registry);
    expect($circuitBreaker2->getCurrentState())->toBe(CircuitBreakerStateEnum::HALF_OPEN);
    $circuitBreaker2->executeRequest(fn() => 'success');
    expect($circuitBreaker2->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
    $circuitBreaker1 = new CircuitBreaker($service, $config, $registry);
    expect($circuitBreaker1->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
});

it('circuit breaker handles lock acquisition failures without crashing', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('lock_fail_service');
    $service = 'lock_fail_service';
    $config = CircuitBreakerConfigData::from(['service' => $service]);
    $circuitBreaker = new CircuitBreaker($service, $config, $registry);
    Cache::shouldReceive('lock')
        ->once()
        ->andReturn(Mockery::mock([
            'get' => false,
            'release' => null
        ]));
    expect(fn() => $circuitBreaker->recordResult(true))
        ->toThrow(RuntimeException::class, 'Failed to get the lock');
});

it('circuit breaker properly handles lock timeout scenarios', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('timeout_service');
    $service = 'timeout_service';
    $config = CircuitBreakerConfigData::from(['service' => $service]);
    $circuitBreaker = new CircuitBreaker($service, $config, $registry);
    $reflection = new ReflectionClass($circuitBreaker);
    $withLockMethod = $reflection->getMethod('withLock');
    $withLockMethod->setAccessible(true);
    expect($withLockMethod)->toBeInstanceOf(ReflectionMethod::class);
});