# Saloon Circuit Breaker

A robust circuit breaker plugin for Saloon HTTP client.

## Features

- **State Management**: Closed, Open, and Half-Open states with automatic transitions
- **Fault Tolerance**: Protects against cascading failures from unreliable external APIs
- **Type Safety**: Built with Spatie Data DTOs for comprehensive type safety
- **Thread Safe**: Cache locks prevent race conditions in high-concurrency scenarios
- **Laravel Integration**: Cache persistence and Artisan commands
- **Monitoring**: Built-in status command for circuit health
- **Configurable**: Flexible per-service configuration options

## Installation

```bash
composer require agrovista/saloon-circuit-breaker
```

## Quick Start

> **Note:** Each circuit breaker requires a unique `service` name in the configuration to track state independently.

**Add the trait to your Saloon Connector:**

```php
use Agrovista\SaloonCircuitBreaker\CircuitBreakerPlugin;
use Saloon\Http\Connector;

class MyAPIConnector extends Connector
{
    use CircuitBreakerPlugin;

    public function __construct()
    {
        $this->withCircuitBreaker([
            'service' => 'my_api',
            'failure_threshold' => 3,
            'timeout' => 600,
        ]);
    }
}
```

## Circuit Breaker States

### Closed (Normal Operation)

- All requests pass through to the external API
- Monitors success/failure rates
- Opens when failure threshold is exceeded

### Open (Protection Mode)

- Blocks all requests immediately
- Throws `CircuitOpenException`
- Transitions to Half-Open after timeout

### Half-Open (Recovery Testing)

- Allows limited test requests
- Closes on success threshold
- Reopens if requests still fail

## Configuration

All configuration is done directly in your connector. The following options are available with their default values:

```php
$this->withCircuitBreaker([
    'service' => 'my_api',
    'failure_threshold' => 5,
    'success_threshold' => 3,
    'timeout' => 60,
    'half_open_attempts' => 3,
    'cache_prefix' => 'circuit_breaker:',
    'cache_ttl' => 3600,
]);
```

### Per-Service Configuration

```php
$this->withCircuitBreaker([
    'service' => 'my_api',
    'failure_threshold' => 3,
    'success_threshold' => 2,
    'timeout' => 300,
]);
```

## Monitoring

### Status Command

View all circuit breaker statuses:

```bash
php artisan saloon-circuit:status
```

Sample output:

```
┌─────────────────┬─────────────┬──────────────┬────────────┬─────────────────────┐
│ Service         │ State       │ Success Rate │ Failures   │ Last Failure        │
├─────────────────┼─────────────┼──────────────┼────────────┼─────────────────────┤
│ my_api          │ OPEN        │ 23%          │ 12/15      │ 2 minutes ago       │
└─────────────────┴─────────────┴──────────────┴────────────┴─────────────────────┘
```

## Requirements

- PHP 8.3+
- Laravel 11.0+, 12.0+
- Saloon 3.0+
- Redis 3.0+

## Testing

Run the test suite:

```bash
./vendor/bin/pest
```

Run static analysis:

```bash
./vendor/bin/phpstan analyse
```
