<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
    protected function refreshApplication()
    {
        parent::refreshApplication();
        if (! app()->environment('testing') || (config('database.default') === 'mysql'
            && ! preg_match('/^airline_test_[a-zA-Z0-9_]+$/D', config('database.connections.mysql.database')))) {
            throw new \RuntimeException('Refusing tests outside testing or a dedicated airline_test_* MySQL database.');
        }
    }
}
