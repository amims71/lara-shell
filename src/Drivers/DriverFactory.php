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

    /** Whether the warm-fork ForkingDriver can run here (Unix + pcntl + posix). */
    public static function supportsForking(): bool
    {
        return PHP_OS_FAMILY !== 'Windows'
            && function_exists('pcntl_fork')
            && function_exists('posix_kill')
            && function_exists('posix_setsid')
            && function_exists('stream_socket_pair');
    }

    public function make(): Driver
    {
        $driver = $this->app->bound('config')
            ? $this->app->make('config')->get('lara-shell.driver', 'auto')
            : 'auto';

        if ($driver !== 'local' && ($driver === 'forking' || $driver === 'auto') && self::supportsForking()) {
            return $this->app->make(ForkingDriver::class);
        }

        return $this->app->make(LocalDriver::class);
    }
}
