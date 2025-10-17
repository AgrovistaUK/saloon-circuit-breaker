<?php

declare(strict_types=1);

namespace Agrovista\SaloonCircuitBreaker\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class CircuitBreakerStatusData extends Data
{
    public function __construct(
        public string $service,
        public string $state,
        public CircuitBreakerMetricsData $metrics,
        public CircuitBreakerConfigData $config,
        public Carbon $timestamp,
    ) {}
}