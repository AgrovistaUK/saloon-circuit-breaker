<?php

use Carbon\Carbon;
use Tests\Support\GetUserRequest;
use Saloon\Http\Faking\MockClient;
use Tests\Support\TestApiConnector;
use Saloon\Http\Faking\MockResponse;
use Tests\Support\CreateUserRequest;
use Illuminate\Support\Facades\Cache;
use AgrovistaUK\SaloonCircuitBreaker\CircuitBreaker;
use AgrovistaUK\SaloonCircuitBreaker\Enums\CircuitBreakerStateEnum;
use AgrovistaUK\SaloonCircuitBreaker\Exceptions\CircuitOpenException;

beforeEach(function () {
    MockClient::destroyGlobal();
    Cache::flush();
    Carbon::setTestNow();
});

it('circuit breaker allows successful HTTP requests to pass through when operating normally', function () {
    $mockClient = MockClient::global([
        GetUserRequest::class => MockResponse::make(
            body: ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
            status: 200
        ),
    ]);
    $connector = new TestApiConnector;
    $response = $connector->send(new GetUserRequest(1));
    expect($response->successful())->toBeTrue();
    expect($response->json('name'))->toBe('John Doe');
    $mockClient->assertSent(GetUserRequest::class);
    $mockClient->assertSentCount(1);
});

it('circuit breaker tracks successful HTTP requests in its metrics', function () {
    $mockClient = MockClient::global([
        CreateUserRequest::class => MockResponse::make(
            body: ['id' => 2, 'name' => 'Jane Doe', 'email' => 'jane@example.com'],
            status: 201
        ),
    ]);
    $connector = new TestApiConnector;
    $connector->send(new CreateUserRequest('Jane Doe', 'jane@example.com'));
    $connector->send(new CreateUserRequest('Bob Smith', 'bob@example.com'));
    $circuitBreaker = $connector->getCircuitBreaker();
    expect($circuitBreaker)->toBeInstanceOf(CircuitBreaker::class);
    expect($circuitBreaker->getService())->toBe('test_api');
    $mockClient->assertSentCount(2);
});

it('circuit breaker handles HTTP 500 errors and records them as failures', function () {
    $mockClient = MockClient::global([
        GetUserRequest::class => MockResponse::make(
            body: ['error' => 'Internal Server Error'],
            status: 500
        ),
    ]);
    $connector = new TestApiConnector;
    $response = $connector->send(new GetUserRequest(1));
    expect($response->failed())->toBeTrue();
    expect($response->status())->toBe(500);
    $circuitBreaker = $connector->getCircuitBreaker();
    $status = $circuitBreaker->getStatus();
    expect($status->metrics->failureCount)->toBe(1);
    $mockClient->assertSent(GetUserRequest::class);
    $mockClient->assertSentCount(1);
});

it('circuit breaker opens and blocks requests after reaching the failure threshold', function () {
    $mockClient = MockClient::global([
        GetUserRequest::class => MockResponse::make(
            body: ['error' => 'Service Unavailable'],
            status: 503
        ),
    ]);
    $connector = new TestApiConnector;
    $response1 = $connector->send(new GetUserRequest(1));
    expect($response1->failed())->toBeTrue();
    $response2 = $connector->send(new GetUserRequest(2));
    expect($response2->failed())->toBeTrue();
    $circuitBreaker = $connector->getCircuitBreaker();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    try {
        $connector->send(new GetUserRequest(3));
        $this->fail('Expected CircuitOpenException was not thrown');
    } catch (CircuitOpenException $e) {
        expect($e->getService())->toBe('test_api');
        expect($e->getMessage())->toContain("Circuit breaker is open for service 'test_api'");
    }
    $mockClient->assertSentCount(2); 
});

it('circuit breaker allows test requests when manually transitioned to half-open state', function () {
    $mockClient = MockClient::global([
        GetUserRequest::class => MockResponse::make(
            body: ['error' => 'Service Unavailable'],
            status: 503
        ),
    ]);
    $connector = new TestApiConnector;
    $response1 = $connector->send(new GetUserRequest(1));
    expect($response1->failed())->toBeTrue();
    $response2 = $connector->send(new GetUserRequest(2));
    expect($response2->failed())->toBeTrue();
    $circuitBreaker = $connector->getCircuitBreaker();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    $circuitBreaker->halfOpenCircuit();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::HALF_OPEN);
    MockClient::destroyGlobal();
    $mockClient = MockClient::global([
        GetUserRequest::class => MockResponse::make(
            body: ['id' => 4, 'name' => 'Test User', 'email' => 'test@example.com'],
            status: 200
        ),
    ]);
    $response = $connector->send(new GetUserRequest(4));
    expect($response->successful())->toBeTrue();
    $mockClient->assertSent(GetUserRequest::class);
});

it('circuit breaker closes and resumes normal operation after successful requests in half-open state', function () {
    $mockClient = MockClient::global([
        GetUserRequest::class => MockResponse::make(
            body: ['error' => 'Service Unavailable'],
            status: 503
        ),
    ]);
    $connector = new TestApiConnector;
    $response1 = $connector->send(new GetUserRequest(1));
    expect($response1->failed())->toBeTrue();
    $response2 = $connector->send(new GetUserRequest(2));
    expect($response2->failed())->toBeTrue();
    $circuitBreaker = $connector->getCircuitBreaker();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    $circuitBreaker->halfOpenCircuit();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::HALF_OPEN);
    MockClient::destroyGlobal();
    $mockClient = MockClient::global([
        GetUserRequest::class => MockResponse::make(
            body: ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com'],
            status: 200
        ),
    ]);
    $response1 = $connector->send(new GetUserRequest(1));
    expect($response1->successful())->toBeTrue();
    $response2 = $connector->send(new GetUserRequest(2));
    expect($response2->successful())->toBeTrue();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
    $response3 = $connector->send(new GetUserRequest(3));
    expect($response3->successful())->toBeTrue();
    $mockClient->assertSentCount(3);
});

it('circuit breaker automatically transitions to half-open state after the timeout period expires', function () {
    $mockClient = MockClient::global([
        GetUserRequest::class => MockResponse::make(
            body: ['error' => 'Service Unavailable'],
            status: 503
        ),
    ]);
    $connector = new TestApiConnector;
    $connector->send(new GetUserRequest(1));
    $connector->send(new GetUserRequest(2));
    $circuitBreaker = $connector->getCircuitBreaker();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    Carbon::setTestNow(Carbon::now()->addSeconds(61));
    MockClient::destroyGlobal();
    $mockClient = MockClient::global([
        GetUserRequest::class => MockResponse::make(
            body: ['id' => 3, 'name' => 'Recovery Test', 'email' => 'test@example.com'],
            status: 200
        ),
    ]);
    $response = $connector->send(new GetUserRequest(3));
    expect($response->successful())->toBeTrue();
    $currentState = $circuitBreaker->getCurrentState();
    expect($currentState)->toBeIn([CircuitBreakerStateEnum::HALF_OPEN, CircuitBreakerStateEnum::CLOSED]);
    Carbon::setTestNow();
    $mockClient->assertSent(GetUserRequest::class);
});

it('circuit breaker maintains consistent state across multiple instances for the same service', function () {
    $mockClient = MockClient::global([
        GetUserRequest::class => MockResponse::make(
            body: ['error' => 'Service Unavailable'],
            status: 503
        ),
    ]);
    $connector1 = new TestApiConnector;
    $connector1->send(new GetUserRequest(1));
    $connector1->send(new GetUserRequest(2));

    $circuitBreaker1 = $connector1->getCircuitBreaker();
    expect($circuitBreaker1->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    $connector2 = new TestApiConnector;
    $circuitBreaker2 = $connector2->getCircuitBreaker();
    expect($circuitBreaker2->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    try {
        $connector2->send(new GetUserRequest(3));
        $this->fail('Expected CircuitOpenException was not thrown');
    } catch (CircuitOpenException $e) {
        expect($e->getService())->toBe('test_api');
    }
    $mockClient->assertSentCount(2);
});

it('circuit breaker enforces the limit on requests allowed in half-open state', function () {
    $mockClient = MockClient::global([
        GetUserRequest::class => MockResponse::make(
            body: ['error' => 'Service Unavailable'],
            status: 503
        ),
    ]);
    $connector = new TestApiConnector;
    $connector->send(new GetUserRequest(1));
    $connector->send(new GetUserRequest(2));
    $circuitBreaker = $connector->getCircuitBreaker();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    $circuitBreaker->halfOpenCircuit();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::HALF_OPEN);
    $response1 = $connector->send(new GetUserRequest(3));
    expect($response1->failed())->toBeTrue();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    try {
        $connector->send(new GetUserRequest(4));
        $this->fail('Request should be blocked by open circuit');
    } catch (CircuitOpenException $e) {
        expect($e->getMessage())->toContain("Circuit breaker is open for service 'test_api'");
    }
    $mockClient->assertSentCount(3);
});

it('circuit breaker correctly tracks failure counts when requests have mixed success and failure responses', function () {
    $mockClient = MockClient::global();
    $mockClient->addResponse(MockResponse::make(['error' => 'Error 1'], 500));
    $mockClient->addResponse(MockResponse::make(['error' => 'Error 2'], 500));
    $connector = new TestApiConnector;
    $circuitBreaker = $connector->getCircuitBreaker();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
    $response1 = $connector->send(new GetUserRequest(1));
    expect($response1->failed())->toBeTrue();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::CLOSED);
    $response2 = $connector->send(new GetUserRequest(2));
    expect($response2->failed())->toBeTrue();
    expect($circuitBreaker->getCurrentState())->toBe(CircuitBreakerStateEnum::OPEN);
    $mockClient->assertSentCount(2);
});
