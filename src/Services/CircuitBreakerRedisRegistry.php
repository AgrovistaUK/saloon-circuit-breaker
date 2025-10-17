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

    private function redis(): ?Connection
    {
        if (!$this->redis) {
            try {
                $this->redis = Redis::connection();
            } catch (\Exception) {
                return null;
            }
        }
        return $this->redis;
    }

    public function registerService(string $service): void
    {
        $redis = $this->redis();
        if (!$redis) return;
        
        $redis->sadd($this->registryKey, $service);
        $redis->expire($this->registryKey, 86400);
    }

    public function getActiveServices(): array
    {
        $redis = $this->redis();
        return $redis ? $redis->smembers($this->registryKey) : [];
    }

    public function isServiceRegistered(string $service): bool
    {
        $redis = $this->redis();
        return $redis ? $redis->sismember($this->registryKey, $service) : false;
    }

    public function unregisterService(string $service): void
    {
        $redis = $this->redis();
        if (!$redis) return;
        
        $redis->srem($this->registryKey, $service);
    }
}
