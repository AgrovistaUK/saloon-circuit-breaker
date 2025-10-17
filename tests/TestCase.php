<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Agrovista\SaloonCircuitBreaker\SaloonCircuitBreakerServiceProvider;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function getPackageProviders($app)
    {
        return [
            SaloonCircuitBreakerServiceProvider::class,
            \Spatie\LaravelData\LaravelDataServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cache.default', 'array');
        
        // Laravel Data configuration with proper normalizers
        $app['config']->set('data', [
            'validation_strategy' => 'always',
            'normalizers' => [
                \Spatie\LaravelData\Normalizers\ModelNormalizer::class,
                \Spatie\LaravelData\Normalizers\FormRequestNormalizer::class,
                \Spatie\LaravelData\Normalizers\ArrayableNormalizer::class,
                \Spatie\LaravelData\Normalizers\ObjectNormalizer::class,
                \Spatie\LaravelData\Normalizers\ArrayNormalizer::class,
                \Spatie\LaravelData\Normalizers\JsonNormalizer::class,
            ],
            'transformers' => [],
            'casts' => [
                \DateTimeInterface::class => \Spatie\LaravelData\Casts\DateTimeInterfaceCast::class,
                \BackedEnum::class => \Spatie\LaravelData\Casts\EnumCast::class,
            ],
            'rule_inferrers' => [],
            'max_transformation_depth' => 512,
            'throw_when_max_transformation_depth_reached' => true,
        ]);
        
        $app['config']->set('saloon-circuit-breaker.defaults', [
            'failure_threshold' => 5,
            'success_threshold' => 3,
            'timeout' => 60,
            'half_open_attempts' => 3,
            'cache_prefix' => 'circuit_breaker:',
            'cache_ttl' => 3600,
        ]);
    }
}
