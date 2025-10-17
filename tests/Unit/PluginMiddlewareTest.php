<?php

use Tests\Support\GetUserRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Illuminate\Support\Facades\Cache;
use Tests\Support\BasicTestConnector;
use Agrovista\SaloonCircuitBreaker\Enums\CircuitBreakerStateEnum;
use Agrovista\SaloonCircuitBreaker\Exceptions\CircuitOpenException;

beforeEach(function () {
    MockClient::destroyGlobal();
    Cache::flush();
});

it('Saloon plugin blocks HTTP requests when the circuit breaker is open', function () {
    $connector = new BasicTestConnector();
    $connector->withCircuitBreaker([
        'service' => 'test_service',
        'failure_threshold' => 1,
    ]);
    $circuitBreaker = $connector->getCircuitBreaker();
    $circuitBreaker->recordResult(false);
    $circuitBreaker->openCircuit();
    MockClient::global([
        GetUserRequest::class => MockResponse::make(['data' => 'success'], 200),
    ]);
    expect(fn() => $connector->send(new GetUserRequest(1)))->toThrow(CircuitOpenException::class);
});

it('Saloon plugin records successful HTTP responses in the circuit breaker metrics', function () {
    $connector = new BasicTestConnector();
    $connector->withCircuitBreaker(['service' => 'test_service']);
    MockClient::global([
        GetUserRequest::class => MockResponse::make(['data' => 'success'], 200),
    ]);
    $response = $connector->send(new GetUserRequest(1));
    expect($response->successful())->toBeTrue();
    $circuitBreaker = $connector->getCircuitBreaker();
    $status = $circuitBreaker->getStatus();
    expect($status->metrics->successCount)->toBe(1);
    expect($status->metrics->failureCount)->toBe(0);
});

it('Saloon plugin records failed HTTP responses in the circuit breaker metrics', function () {
    $connector = new BasicTestConnector();
    $connector->withCircuitBreaker([
        'service' => 'test_service',
        'failure_threshold' => 2,
    ]);
    MockClient::global([
        GetUserRequest::class => MockResponse::make(['error' => 'server error'], 500),
    ]);
    $response = $connector->send(new GetUserRequest(1));
    expect($response->failed())->toBeTrue();
    $circuitBreaker = $connector->getCircuitBreaker();
    $status = $circuitBreaker->getStatus();
    expect($status->metrics->successCount)->toBe(0);
    expect($status->metrics->failureCount)->toBe(1);
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
});

it('Saloon plugin opens the circuit breaker after reaching the failure threshold', function () {
    $connector = new BasicTestConnector();
    $connector->withCircuitBreaker([
        'service' => 'test_service',
        'failure_threshold' => 2,
    ]);
    MockClient::global([
        GetUserRequest::class => MockResponse::make(['error' => 'error1'], 500),
    ]);
    $response1 = $connector->send(new GetUserRequest(1));
    expect($response1->failed())->toBeTrue();
    $circuitBreaker = $connector->getCircuitBreaker();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
    MockClient::destroyGlobal();
    MockClient::global([
        GetUserRequest::class => MockResponse::make(['error' => 'error2'], 500),
    ]);
    $response2 = $connector->send(new GetUserRequest(2));
    expect($response2->failed())->toBeTrue();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
});

it('Saloon plugin allows HTTP requests when the circuit breaker is in half-open state', function () {
    $connector = new BasicTestConnector();
    $connector->withCircuitBreaker([
        'service' => 'test_service',
        'failure_threshold' => 1,
        'success_threshold' => 1,
    ]);
    $circuitBreaker = $connector->getCircuitBreaker();
    $circuitBreaker->recordResult(false);
    $circuitBreaker->openCircuit();
    $circuitBreaker->halfOpenCircuit();
    MockClient::global([
        GetUserRequest::class => MockResponse::make(['data' => 'recovery'], 200),
    ]);
    $response = $connector->send(new GetUserRequest(1));
    expect($response->successful())->toBeTrue();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
});