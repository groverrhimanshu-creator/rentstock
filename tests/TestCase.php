<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /* Safety: the tests wipe the database, so they refuse to run on anything that is not a "_test" database. */
    protected function setUp(): void
    {
        parent::setUp();
        $name = (string) DB::connection()->getDatabaseName();
        if (!str_ends_with($name, '_test')) {
            $this->fail("Refusing to run: database '$name' is not a test database. Create 'rentstock_test' and check phpunit.xml.");
        }
    }
}
