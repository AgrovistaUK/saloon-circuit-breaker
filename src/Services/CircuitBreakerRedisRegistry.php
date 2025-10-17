<?php
declare(strict_types=1);

namespace AgrovistaUK\SaloonCircuitBreaker\Services;

use AgrovistaUK\SaloonCircuitBreaker\Enums\CircuitBreakerCacheEnum;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class CircuitBreakerRedisRegistry
{
    private ?Connection $redis = null;
    private string $registryKey = CircuitBreakerCacheEnum::ACTIVE_SERVICES->value;

    private function redis(): Connection
    {
        if (!$this->redis) {
            $this->redis = Redis::connection();
        }
        return $this->redis;
    }

    public function registerService(string $service): void
    {
        $this->redis()->sadd($this->registryKey, $service);
        $this->redis()->expire($this->registryKey, 86400);
    }

    public function getActiveServices(): array
    {
        return $this->redis()->smembers($this->registryKey);
    }

    public function isServiceRegistered(string $service): bool
    {
        return $this->redis()->sismember($this->registryKey, $service);
    }

    public function unregisterService(string $service): void
    {
        $this->redis()->srem($this->registryKey, $service);
    }
}
