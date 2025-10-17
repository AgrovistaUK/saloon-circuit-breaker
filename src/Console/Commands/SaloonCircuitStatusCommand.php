<?php

declare(strict_types=1);

namespace Agrovista\SaloonCircuitBreaker\Console\Commands;

use Agrovista\SaloonCircuitBreaker\CircuitBreaker;
use Agrovista\SaloonCircuitBreaker\Data\CircuitBreakerConfigData;
use Agrovista\SaloonCircuitBreaker\Data\CircuitBreakerStatusData;
use Agrovista\SaloonCircuitBreaker\Enums\CircuitBreakerCacheEnum;
use Agrovista\SaloonCircuitBreaker\Services\CircuitBreakerRedisRegistry;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SaloonCircuitStatusCommand extends Command
{
    protected $signature = 'saloon-circuit:status';
    protected $description = 'Display the status of all Saloon circuit breakers';

    public function __construct(protected CircuitBreakerRedisRegistry $circuitBreakerRedisRegistry) {
        parent::__construct();
    }

    public function handle(): int
    {
        $services = $this->getAllCircuitBreakerServices();
        if (empty($services)) {
            $this->info('No circuit breakers found.');
            return self::SUCCESS;
        }
        $rows = [];
        foreach ($services as $service) {
            $status = $this->getServiceStatus($service);
            if ($status) {
                $rows[] = [
                    $status->service,
                    $this->formatState($status->state),
                    $status->metrics->getSuccessRate() . '%',
                    $status->metrics->failureCount . '/' . $status->metrics->getTotalRequests(),
                    $this->formatLastFailure($status->metrics->lastFailureTime),
                ];
            }
        }
        if (empty($rows)) {
            $this->info('No circuit breaker data found.');
            return self::SUCCESS;
        }
        $this->table([
            'Service',
            'State', 
            'Success Rate',
            'Failures',
            'Last Failure'
        ], $rows);
        return self::SUCCESS;
    }

    protected function getAllCircuitBreakerServices(): array
    {
        $services = $this->circuitBreakerRedisRegistry->getActiveServices();
        $activeServices = [];
        foreach ($services as $service) {
            $stateKey = CircuitBreakerCacheEnum::PREFIX->value . $service . CircuitBreakerCacheEnum::STATE_SUFFIX->value;
            if(Cache::has($stateKey)) {
                $activeServices[] = $service;
            }
        }
        return $activeServices;
    }

    protected function getServiceStatus(string $service): ?CircuitBreakerStatusData
    {
        try {
            $config = CircuitBreakerConfigData::defaults();
            $breaker = new CircuitBreaker($service, $config, $this->circuitBreakerRedisRegistry);
            return $breaker->getStatus();
        } catch (\Exception $e) {
            $this->warn("Failed to get status for service '{$service}': " . $e->getMessage());
            return null;
        }
    }

    private function formatState(string $state): string
    {
        return match ($state) {
            'open' => 'OPEN',
            'closed' => 'CLOSED',
            'half-open' => 'HALF-OPEN',
            default => strtoupper($state),
        };
    }

    private function formatLastFailure(?Carbon $lastFailureTime): string
    {
        return $lastFailureTime?->diffForHumans() ?? 'Never';
    }
}