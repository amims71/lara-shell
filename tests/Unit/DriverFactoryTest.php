<?php

use Amims71\LaraShell\Drivers\DriverFactory;
use Amims71\LaraShell\Drivers\ForkingDriver;
use Amims71\LaraShell\Drivers\LocalDriver;

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

it('reports forking support as a bool', function () {
    expect(DriverFactory::supportsForking())->toBeBool();
});

it('makes a LocalDriver when the driver is forced to local', function () {
    config(['lara-shell.driver' => 'local']);

    expect(app(DriverFactory::class)->make())->toBeInstanceOf(LocalDriver::class);
});

it('makes the warm-fork driver under auto when forking is supported', function () {
    config(['lara-shell.driver' => 'auto']);

    $driver = app(DriverFactory::class)->make();

    if (DriverFactory::supportsForking()) {
        expect($driver)->toBeInstanceOf(ForkingDriver::class);
    } else {
        expect($driver)->toBeInstanceOf(LocalDriver::class);
    }
});
