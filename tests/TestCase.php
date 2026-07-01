<?php

namespace Amims71\LaraShell\Tests;

use Amims71\LaraShell\LaraShellServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaraShellServiceProvider::class,
        ];
    }
}
