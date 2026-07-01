<?php

use Amims71\LaraShell\Drivers\ForkingDriver;
use Amims71\LaraShell\Drivers\LocalDriver;
use Amims71\LaraShell\Jobs\FileJobRegistry;
use Amims71\LaraShell\Jobs\Job;
use Amims71\LaraShell\Jobs\ProcessTree;
use Amims71\LaraShell\Support\Paths;
use Illuminate\Contracts\Console\Kernel;

/** A LocalDriver that records background() calls, so delegation can be asserted. */
function spyLocalDriver(): LocalDriver
{
    $paths = new Paths(sys_get_temp_dir().'/lara-shell-fork-'.bin2hex(random_bytes(3)));
    $registry = new FileJobRegistry($paths->jobsFile(), new ProcessTree());

    return new class(PHP_BINARY, 'artisan', $registry, $paths) extends LocalDriver {
        public array $backgrounded = [];

        public function background(array $argv): Job
        {
            $this->backgrounded[] = $argv;

            return new Job('spy', 111, implode(' ', $argv), 'running', time(), null, '/tmp/spy.log');
        }
    };
}

it('delegates background() to the wrapped LocalDriver', function () {
    $local = spyLocalDriver();
    $driver = new ForkingDriver($local, app(Kernel::class), PHP_BINARY, 'artisan');

    $job = $driver->background(['queue:work']);

    expect($local->backgrounded)->toBe([['queue:work']])
        ->and($job->command)->toBe('queue:work');
});

it('delegates jobs() to the wrapped LocalDriver registry', function () {
    $local = spyLocalDriver();
    $driver = new ForkingDriver($local, app(Kernel::class), PHP_BINARY, 'artisan');

    expect($driver->jobs())->toBe($local->jobs());
});

it('reloadCommand re-execs a fresh artisan shell', function () {
    $local = spyLocalDriver();
    $driver = new ForkingDriver($local, app(Kernel::class), '/usr/bin/php', '/app/artisan');

    expect($driver->reloadCommand())->toBe(['/usr/bin/php', ['/app/artisan', 'shell']]);
});
