<?php

declare(strict_types=1);

namespace Agrovista\SaloonCircuitBreaker\Data;

use Spatie\LaravelData\Data;
use Agrovista\SaloonCircuitBreaker\Enums\CircuitBreakerCacheEnum;

class CircuitBreakerConfigData extends Data
{
    public function __construct(
        public int $failure_threshold = 5,
        public int $success_threshold = 3,
        public int $timeout = 60,
        public int $half_open_attempts = 3,
        public string $cache_prefix = CircuitBreakerCacheEnum::PREFIX->value,
        public int $cache_ttl = 3600,
    ) {}

    public static function defaults(): self
    {
        return new self();
    }

    public function merge(array $overrides): self
    {
        return self::from(array_merge($this->toArray(), $overrides));
    }
}