<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->preventTestingAgainstApplicationDatabase();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    private function preventTestingAgainstApplicationDatabase(): void
    {
        if (! $this->app->environment('testing')) {
            throw new \RuntimeException('Tests must run with APP_ENV=testing.');
        }

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection === 'sqlite' && $database === ':memory:') {
            return;
        }

        if (is_string($database) && str_contains($database, 'test')) {
            return;
        }

        throw new \RuntimeException(
            "Refusing to run tests against [{$connection}] database [{$database}]. Configure an in-memory SQLite or dedicated test database.",
        );
    }
}
