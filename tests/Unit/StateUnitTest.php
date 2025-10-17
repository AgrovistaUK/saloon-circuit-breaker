<?php

use Agrovista\SaloonCircuitBreaker\CircuitBreaker;
use Agrovista\SaloonCircuitBreaker\Data\CircuitBreakerConfigData;
use Agrovista\SaloonCircuitBreaker\States\ClosedState;
use Agrovista\SaloonCircuitBreaker\States\OpenState;
use Agrovista\SaloonCircuitBreaker\States\HalfOpenState;
use Agrovista\SaloonCircuitBreaker\Enums\CircuitBreakerStateEnum;
use Agrovista\SaloonCircuitBreaker\Services\CircuitBreakerRedisRegistry;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::store('redis')->flush();
});

it('closed state allows all requests to pass through normally', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test');
    $config = CircuitBreakerConfigData::from(['service' => 'test']);
    $circuitBreaker = new CircuitBreaker('test', $config, $registry);
    $closedState = new ClosedState();
    expect($closedState->canExecuteRequest($circuitBreaker))->toBeTrue();
});

it('closed state records successful requests but stays closed', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test');
    $config = CircuitBreakerConfigData::from(['service' => 'test']);
    $circuitBreaker = new CircuitBreaker('test', $config, $registry);
    $closedState = new ClosedState();
    $closedState->handleSuccessfulRequest($circuitBreaker);
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
    $status = $circuitBreaker->getStatus();
    expect($status->metrics->successCount)->toBe(1);
});

it('closed state opens the circuit after too many consecutive failures', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test');
    $config = CircuitBreakerConfigData::from([
        'service' => 'test',
        'failure_threshold' => 2
    ]);
    $circuitBreaker = new CircuitBreaker('test', $config, $registry);
    $closedState = new ClosedState();
    $closedState->handleFailedRequest($circuitBreaker);
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
    $closedState->handleFailedRequest($circuitBreaker);
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
});

it('closed state correctly identifies itself as the closed state', function () {
    $closedState = new ClosedState();
    expect($closedState->getState())->toBe(CircuitBreakerStateEnum::CLOSED);
    expect($closedState->getStateName())->toBe('closed');
});

it('open state blocks all requests while waiting for the timeout period', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test');
    $config = CircuitBreakerConfigData::from(['service' => 'test']);
    $circuitBreaker = new CircuitBreaker('test', $config, $registry);
    $openState = new OpenState();
    $circuitBreaker->openCircuit();
    expect($openState->canExecuteRequest($circuitBreaker))->toBeFalse();
});

it('open state allows requests to test service recovery after the timeout period', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test');
    $config = CircuitBreakerConfigData::from([
        'service' => 'test',
        'timeout' => 1
    ]);
    $circuitBreaker = new CircuitBreaker('test', $config, $registry);
    $openState = new OpenState();
    $circuitBreaker->openCircuit();
    $circuitBreaker->getStatus()->metrics->circuitOpenedAt = now()->subSeconds(2);
    expect($openState->canExecuteRequest($circuitBreaker))->toBeTrue();
});
it('open state correctly identifies itself as the open state', function () {
    $openState = new OpenState();
    expect($openState->getState())->toBe(CircuitBreakerStateEnum::OPEN);
    expect($openState->getStateName())->toBe('open');
});
it('half-open state allows a limited number of test requests to check service recovery', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test');
    $config = CircuitBreakerConfigData::from([
        'service' => 'test',
        'half_open_attempts' => 3
    ]);
    $circuitBreaker = new CircuitBreaker('test', $config, $registry);
    $halfOpenState = new HalfOpenState();
    $circuitBreaker->halfOpenCircuit();
    expect($halfOpenState->canExecuteRequest($circuitBreaker))->toBeTrue();
});

it('half-open state blocks requests once the attempt limit is reached', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test');
    $config = CircuitBreakerConfigData::from([
        'service' => 'test',
        'half_open_attempts' => 2
    ]);
    $circuitBreaker = new CircuitBreaker('test', $config, $registry);
    $halfOpenState = new HalfOpenState();
    $circuitBreaker->halfOpenCircuit();
    $circuitBreaker->executeRequest(fn() => 'test1');
    $circuitBreaker->executeRequest(fn() => 'test2');
    expect($halfOpenState->canExecuteRequest($circuitBreaker))->toBeFalse();
});

it('half-open state closes the circuit and resumes normal operation after enough successful requests', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test');
    $config = CircuitBreakerConfigData::from([
        'service' => 'test',
        'success_threshold' => 2
    ]);
    $circuitBreaker = new CircuitBreaker('test', $config, $registry);
    $halfOpenState = new HalfOpenState();
    $circuitBreaker->halfOpenCircuit();
    $halfOpenState->handleSuccessfulRequest($circuitBreaker);
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::HALF_OPEN);
    $halfOpenState->handleSuccessfulRequest($circuitBreaker);
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
});

it('half-open state immediately reopens the circuit when a request fails', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test');
    $config = CircuitBreakerConfigData::from(['service' => 'test']);
    $circuitBreaker = new CircuitBreaker('test', $config, $registry);
    $halfOpenState = new HalfOpenState();
    $circuitBreaker->halfOpenCircuit();
    $halfOpenState->handleFailedRequest($circuitBreaker);
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
});

it('half-open state correctly identifies itself as the half-open state', function () {
    $halfOpenState = new HalfOpenState();
    expect($halfOpenState->getState())->toBe(CircuitBreakerStateEnum::HALF_OPEN);
    expect($halfOpenState->getStateName())->toBe('half-open');
});