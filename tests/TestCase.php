<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Laravel's HandleExceptions bootstrapper resets error_reporting to E_ALL.
     * On PHP 8.4+/8.5 many vendor packages emit E_DEPRECATED notices
     * (e.g. implicitly nullable parameters), which PHPUnit's error handler
     * converts to exceptions and crashes the whole test run.
     * Excluding deprecations after app bootstrap keeps the suite runnable.
     */
    protected function setUp(): void
    {
        parent::setUp();

        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    }
}
