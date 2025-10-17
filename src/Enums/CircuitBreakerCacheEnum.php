<?php

declare(strict_types=1);

namespace AgrovistaUK\SaloonCircuitBreaker\Enums;

enum CircuitBreakerCacheEnum: string
{
    case PREFIX = 'circuit_breaker:';
    case ACTIVE_SERVICES = 'circuit_breaker:active_services';
    case LOCK_SUFFIX = ':lock';
    case STATE_SUFFIX = ':state';
    case METRICS_SUFFIX = ':metrics';
}