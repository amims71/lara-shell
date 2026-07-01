<?php

use Amims71\LaraShell\Drivers\Driver;
use Amims71\LaraShell\Drivers\DriverFactory;
use Amims71\LaraShell\Drivers\LocalDriver;
use Amims71\LaraShell\Jobs\FileJobRegistry;
use Amims71\LaraShell\Jobs\JobRegistry;
use Amims71\LaraShell\Jobs\ProcessTree;
use Amims71\LaraShell\Support\Paths;
use Illuminate\Container\Container;

it('reports daemon support as a bool without throwing', function () {
    expect(DriverFactory::supportsDaemon())->toBeBool();
});

it('agrees with the documented Unix + pcntl + posix + unix-transport probe', function () {
    $expected = PHP_OS_FAMILY !== 'Windows'
        && function_exists('pcntl_fork')
        && function_exists('posix_setsid')
        && function_exists('posix_kill')
        && in_array('unix', stream_get_transports(), true);

    expect(DriverFactory::supportsDaemon())->toBe($expected);
});

it('makes a LocalDriver from the container in Plan 1', function () {
    $container = new Container();

    $paths = new Paths(sys_get_temp_dir());
    $registry = new FileJobRegistry($paths->jobsFile(), new ProcessTree());

    $container->instance(JobRegistry::class, $registry);
    $container->bind(LocalDriver::class, fn () => new LocalDriver(
        PHP_BINARY,
        'artisan',
        $registry,
        $paths,
    ));

    $factory = new DriverFactory($container);
    $driver = $factory->make();

    expect($driver)->toBeInstanceOf(Driver::class)
        ->and($driver)->toBeInstanceOf(LocalDriver::class);
});
