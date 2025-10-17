<?php

declare(strict_types=1);

namespace Agrovista\SaloonCircuitBreaker\Exceptions;

use Agrovista\SaloonCircuitBreaker\Data\CircuitBreakerStatusData;
use Carbon\Carbon;
use Exception;

class CircuitOpenException extends Exception
{
    public function __construct(public readonly string $service, public readonly ?Carbon $nextRetryAt = null, public readonly ?CircuitBreakerStatusData $status = null) {
        $retryMessage = $this->nextRetryAt ? " Circuit will retry {$this->nextRetryAt->diffForHumans()}." : '';
        $message = "Circuit breaker is open for service '{$this->service}'.{$retryMessage}";
        parent::__construct($message);
    }

    public function getService(): string
    {
        return $this->service;
    }
}