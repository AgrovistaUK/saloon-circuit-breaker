<?php

use Agrovista\SaloonCircuitBreaker\CircuitBreaker;
use Agrovista\SaloonCircuitBreaker\Data\CircuitBreakerConfigData;
use Agrovista\SaloonCircuitBreaker\Enums\CircuitBreakerStateEnum;
use Agrovista\SaloonCircuitBreaker\Enums\CircuitBreakerCacheEnum;
use Agrovista\SaloonCircuitBreaker\Services\CircuitBreakerRedisRegistry;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::store('redis')->flush();
});

it('circuit breaker automatically saves its state to cache when it changes', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test_service');
    $service = 'test_service';
    $config = CircuitBreakerConfigData::from(['service' => $service]);
    $circuitBreaker = new CircuitBreaker($service, $config, $registry);
    $circuitBreaker->recordResult(true);
    $circuitBreaker->recordResult(false);
    $circuitBreaker->openCircuit();
    $stateKey = $config->cache_prefix . $service . CircuitBreakerCacheEnum::STATE_SUFFIX->value;
    $metricsKey = $config->cache_prefix . $service . CircuitBreakerCacheEnum::METRICS_SUFFIX->value;
    $cachedState = Cache::get($stateKey);
    $cachedMetrics = Cache::get($metricsKey);
    expect($cachedState['state'])->toBe('open');
    expect($cachedMetrics['successCount'])->toBe(1);
    expect($cachedMetrics['failureCount'])->toBe(1);
    expect($cachedMetrics['circuitOpenedAt'])->not->toBeNull();
});

it('circuit breaker creates proper cache keys for storing state, metrics, and locks', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('my_service');
    $service = 'my_service';
    $config = CircuitBreakerConfigData::from([
        'service' => $service,
        'cache_prefix' => 'custom:'
    ]);
    $circuitBreaker = new CircuitBreaker($service, $config, $registry);
    $reflection = new ReflectionClass($circuitBreaker);
    $getStateKeyMethod = $reflection->getMethod('getStateKey');
    $getMetricsKeyMethod = $reflection->getMethod('getMetricsKey');
    $getLockKeyMethod = $reflection->getMethod('getLockKey');
    $getStateKeyMethod->setAccessible(true);
    $getMetricsKeyMethod->setAccessible(true);
    $getLockKeyMethod->setAccessible(true);
    expect($getStateKeyMethod->invoke($circuitBreaker))->toBe('custom:my_service:state');
    expect($getMetricsKeyMethod->invoke($circuitBreaker))->toBe('custom:my_service:metrics');
    expect($getLockKeyMethod->invoke($circuitBreaker))->toBe('custom:my_service:lock');
});

it('circuit breaker data expires from cache after the configured time-to-live', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test_service');
    $service = 'test_service';
    $config = CircuitBreakerConfigData::from([
        'service' => $service,
        'cache_ttl' => 300
    ]);
    $circuitBreaker = new CircuitBreaker($service, $config, $registry);
    $circuitBreaker->recordResult(true);
    $metricsKey = $config->cache_prefix . $service . CircuitBreakerCacheEnum::METRICS_SUFFIX->value;
    $cachedMetrics = Cache::get($metricsKey);
    expect($cachedMetrics)->not->toBeNull();
    expect($cachedMetrics['successCount'])->toBe(1);
});

it('circuit breaker works normally when starting with no cached data', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->once()->with('test_service');
    $service = 'test_service';
    $config = CircuitBreakerConfigData::from(['service' => $service]);
    $circuitBreaker = new CircuitBreaker($service, $config, $registry);
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
    $status = $circuitBreaker->getStatus();
    expect($status->metrics->successCount)->toBe(0);
    expect($status->metrics->failureCount)->toBe(0);
});

it('multiple circuit breakers for the same service stay in sync', function () {
    $registry = Mockery::mock(CircuitBreakerRedisRegistry::class);
    $registry->shouldReceive('registerService')->twice()->with('shared_service');
    $service = 'shared_service';
    $config = CircuitBreakerConfigData::from(['service' => $service]);
    $circuitBreaker1 = new CircuitBreaker($service, $config, $registry);
    $circuitBreaker1->recordResult(false);
    $circuitBreaker1->recordResult(false);
    $circuitBreaker1->openCircuit();
    $circuitBreaker2 = new CircuitBreaker($service, $config, $registry);
    expect($circuitBreaker2->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    $status2 = $circuitBreaker2->getStatus();
    expect($status2->metrics->failureCount)->toBe(2);
});