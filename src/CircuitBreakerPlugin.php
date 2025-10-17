<?php

declare(strict_types=1);

namespace Agrovista\SaloonCircuitBreaker;

use InvalidArgumentException;
use RuntimeException;
use Saloon\Http\Response;
use Saloon\Http\PendingRequest;
use Agrovista\SaloonCircuitBreaker\Data\CircuitBreakerConfigData;
use Agrovista\SaloonCircuitBreaker\Exceptions\CircuitOpenException;
use Agrovista\SaloonCircuitBreaker\Enums\CircuitBreakerStateEnum;
use Agrovista\SaloonCircuitBreaker\Services\CircuitBreakerRedisRegistry;

trait CircuitBreakerPlugin
{
    protected ?CircuitBreaker $circuitBreaker = null;

    public function bootCircuitBreakerPlugin(): void
    {
        $this->middleware()->onRequest(function (PendingRequest $pendingRequest) {
            $circuitBreaker = $this->getCircuitBreaker();
            if (!$circuitBreaker->canExecuteRequest()) {
                throw new CircuitOpenException(
                    service: $circuitBreaker->getService(),
                    nextRetryAt: $circuitBreaker->getNextRetryAt(),
                    status: $circuitBreaker->getStatus()
                );
            }
        });
        $this->middleware()->onResponse(function (Response $response) {
            $circuitBreaker = $this->getCircuitBreaker();
            if ($response->failed()) {
                $circuitBreaker->recordResult(false);
                if ($circuitBreaker->hasReachedFailureThreshold()) {
                    $circuitBreaker->openCircuit();
                }
                return;
            }
            $circuitBreaker->recordResult(true);
            if ($circuitBreaker->getCurrentState() === CircuitBreakerStateEnum::HALF_OPEN && $circuitBreaker->hasReachedSuccessThreshold()) {
                $circuitBreaker->closeCircuit();
            }
        });

        $this->middleware()->onFatalException(function (\Saloon\Exceptions\Request\FatalRequestException $exception) {
            $circuitBreaker = $this->getCircuitBreaker();
            $circuitBreaker->recordResult(false);
            if ($circuitBreaker->hasReachedFailureThreshold()) {
                $circuitBreaker->openCircuit();
            }
        });
    }

    public function withCircuitBreaker(array $config = []): self
    {
        throw_if(!isset($config['service']), InvalidArgumentException::class, 'Service name is required in circuit breaker configuration');
        $serviceName = $config['service'];
        $circuitConfig = $config;
        unset($circuitConfig['service']);
        $this->circuitBreaker = new CircuitBreaker(
            service: $serviceName,
            circuitBreakerConfigData: CircuitBreakerConfigData::defaults()->merge($circuitConfig),
            redisRegistry: app(CircuitBreakerRedisRegistry::class)
        );
        return $this;
    }

    public function getCircuitBreaker(): CircuitBreaker
    {
        throw_if(!$this->circuitBreaker, RuntimeException::class, 'Circuit breaker not initialised. Call withCircuitBreaker() first.');
        return $this->circuitBreaker;
    }
}