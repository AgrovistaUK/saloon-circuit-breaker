<?php

use Agrovista\SaloonCircuitBreaker\CircuitBreaker;
use Agrovista\SaloonCircuitBreaker\Data\CircuitBreakerConfigData;
use Agrovista\SaloonCircuitBreaker\Exceptions\CircuitOpenException;
use Agrovista\SaloonCircuitBreaker\Enums\CircuitBreakerStateEnum;
use Agrovista\SaloonCircuitBreaker\Services\CircuitBreakerRedisRegistry;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::store('redis')->flush();
});

it('circuit breaker allows successful requests to pass through when operating normally', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test_service');
    $config = CircuitBreakerConfigData::from([
        'service' => 'test_service',
        'failure_threshold' => 2,
    ]);
    $circuitBreaker = new CircuitBreaker('test_service', $config, $registry);
    $result = $circuitBreaker->executeRequest(function () {
        return 'success';
    });
    expect($result)->toBe('success');
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
});

it('circuit breaker re-throws the original exception when a request fails', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test_service');
    $config = CircuitBreakerConfigData::from([
        'service' => 'test_service',
        'failure_threshold' => 2,
    ]);
    $circuitBreaker = new CircuitBreaker('test_service', $config, $registry);
    expect(function () use ($circuitBreaker) {
        $circuitBreaker->executeRequest(function () {
            throw new Exception('Request failed');
        });
    })->toThrow(Exception::class, 'Request failed');
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
});

it('circuit breaker blocks all requests when the circuit is open to prevent cascading failures', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test_service');
    $config = CircuitBreakerConfigData::from([
        'service' => 'test_service',
        'failure_threshold' => 2,
    ]);
    $circuitBreaker = new CircuitBreaker('test_service', $config, $registry);
    $circuitBreaker->recordResult(false);
    $circuitBreaker->recordResult(false);
    $circuitBreaker->openCircuit();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    expect(function () use ($circuitBreaker) {
        $circuitBreaker->executeRequest(function () {
            return 'should not execute';
        });
    })->toThrow(CircuitOpenException::class);
});

it('circuit breaker tracks the number of attempts made in half-open state', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test_service');
    $config = CircuitBreakerConfigData::from([
        'service' => 'test_service',
        'failure_threshold' => 2,
        'half_open_attempts' => 3,
    ]);
    $circuitBreaker = new CircuitBreaker('test_service', $config, $registry);
    $circuitBreaker->recordResult(false);
    $circuitBreaker->recordResult(false);
    $circuitBreaker->halfOpenCircuit();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::HALF_OPEN);
    $circuitBreaker->executeRequest(function () {
        return 'success';
    });
    $status = $circuitBreaker->getStatus();
    expect($status->metrics->half_open_attempts)->toBe(1);
});

it('circuit breaker closes and resumes normal operation after enough successful requests in half-open state', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test_service');
    $config = CircuitBreakerConfigData::from([
        'service' => 'test_service',
        'failure_threshold' => 2,
        'success_threshold' => 2,
    ]);
    $circuitBreaker = new CircuitBreaker('test_service', $config, $registry);
    $circuitBreaker->recordResult(false);
    $circuitBreaker->recordResult(false);
    $circuitBreaker->halfOpenCircuit();
    $circuitBreaker->executeRequest(function () { return 'success1'; });
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::HALF_OPEN);
    $circuitBreaker->executeRequest(function () { return 'success2'; });
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
});

it('circuit breaker reopens immediately when a request fails in half-open state', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test_service');
    $config = CircuitBreakerConfigData::from([
        'service' => 'test_service',
        'failure_threshold' => 2,
    ]);
    $circuitBreaker = new CircuitBreaker('test_service', $config, $registry);
    $circuitBreaker->recordResult(false);
    $circuitBreaker->recordResult(false);
    $circuitBreaker->halfOpenCircuit();
    expect(function () use ($circuitBreaker) {
        $circuitBreaker->executeRequest(function () {
            throw new Exception('Request failed');
        });
    })->toThrow(Exception::class);
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
});