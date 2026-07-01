<?php

namespace Amims71\LaraShell\Drivers;

use Illuminate\Contracts\Container\Container;

class DriverFactory
{
    public function __construct(private Container $app) {}

    public static function supportsDaemon(): bool
    {
        return PHP_OS_FAMILY !== 'Windows'
            && function_exists('pcntl_fork')
            && function_exists('posix_setsid')
            && function_exists('posix_kill')
            && in_array('unix', stream_get_transports(), true);
    }

    public function make(): Driver
    {
        // Plan 2 adds DaemonDriver here
        return $this->app->make(LocalDriver::class);
    }
}
