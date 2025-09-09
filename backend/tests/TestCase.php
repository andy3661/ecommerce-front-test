<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\CreatesApplication;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure we're using SQLite for testing
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        
        // Run migrations for each test
        $this->artisan('migrate', ['--database' => 'sqlite']);
    }
}
