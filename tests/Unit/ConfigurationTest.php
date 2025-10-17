<?php

use AgrovistaUK\SaloonCircuitBreaker\Data\CircuitBreakerConfigData;
use Tests\Support\BasicTestConnector;

it('circuit breaker configuration uses sensible default values', function () {
    $config = CircuitBreakerConfigData::defaults();
    expect($config->failure_threshold)->toBe(5);
    expect($config->success_threshold)->toBe(3);
    expect($config->timeout)->toBe(60);
    expect($config->half_open_attempts)->toBe(3);
    expect($config->cache_prefix)->toBe('circuit_breaker:');
    expect($config->cache_ttl)->toBe(3600);
});

it('circuit breaker configuration accepts custom values from an array', function () {
    $config = CircuitBreakerConfigData::from([
        'failure_threshold' => 10,
        'success_threshold' => 5,
        'timeout' => 300,
        'cache_prefix' => 'custom:',
    ]);
    expect($config->failure_threshold)->toBe(10);
    expect($config->success_threshold)->toBe(5);
    expect($config->timeout)->toBe(300);
    expect($config->cache_prefix)->toBe('custom:');
    expect($config->half_open_attempts)->toBe(3);
});

it('circuit breaker configuration properly merges default and custom values', function () {
    $config = CircuitBreakerConfigData::defaults();
    $merged = $config->merge(['failure_threshold' => 8, 'timeout' => 120]);
    expect($merged->failure_threshold)->toBe(8);
    expect($merged->timeout)->toBe(120);
    expect($merged->success_threshold)->toBe(3);
});

it('circuit breaker uses default service name when not provided', function () {
    $connector = new BasicTestConnector();
    $connector->withCircuitBreaker([
        'failure_threshold' => 3,
        'success_threshold' => 2,
        'timeout' => 300,
    ]);
    $circuitBreaker = $connector->getCircuitBreaker();
    expect($circuitBreaker->getService())->toBe('basic_test_connector');
});

it('status command shows no circuit breakers when none are registered', function () {
    $this->artisan('saloon-circuit:status')
        ->expectsOutput('No circuit breakers found.')
        ->assertSuccessful();
});

it('circuit breaker status command runs without errors', function () {
    $this->artisan('saloon-circuit:status')
        ->assertSuccessful();
});

it('circuit breaker status command is properly registered with Artisan', function () {
    $this->artisan('list')
        ->expectsOutputToContain('saloon-circuit:status');
});