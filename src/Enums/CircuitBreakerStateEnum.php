<?php

declare(strict_types=1);

namespace Agrovista\SaloonCircuitBreaker\Enums;

enum CircuitBreakerStateEnum: string
{
    case CLOSED = 'closed';
    case OPEN = 'open';
    case HALF_OPEN = 'half-open';
}