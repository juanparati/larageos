<?php

namespace Juanparati\LaraGeos\Tests;

use Juanparati\LaraGeos\LaraGeosServiceProvider;
use Juanparati\LaraGeos\Tests\Concerns\InteractsWithDriverSql;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use InteractsWithDriverSql;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaraGeosServiceProvider::class,
        ];
    }
}
