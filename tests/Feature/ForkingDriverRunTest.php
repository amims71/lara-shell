<?php

use Amims71\LaraShell\Drivers\DriverFactory;
use Amims71\LaraShell\Drivers\ForkingDriver;
use Amims71\LaraShell\Drivers\LocalDriver;
use Amims71\LaraShell\Jobs\FileJobRegistry;
use Amims71\LaraShell\Jobs\ProcessTree;
use Amims71\LaraShell\Support\Paths;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function () {
    if (! DriverFactory::supportsForking()) {
        $this->markTestSkipped('ForkingDriver needs pcntl + posix (Unix).');
    }
});

function makeForkingDriver(): ForkingDriver
{
    $paths = new Paths(sys_get_temp_dir().'/lara-shell-run-'.bin2hex(random_bytes(3)));
    $local = new LocalDriver(PHP_BINARY, 'artisan', new FileJobRegistry($paths->jobsFile(), new ProcessTree()), $paths);

    return new ForkingDriver($local, app(Kernel::class), PHP_BINARY, base_path('artisan'));
}

function registerMarkCommand(): void
{
    app(Kernel::class)->registerCommand(new class extends Command {
        protected $signature = 'lara:mark {--code=0} {--marker=}';

        public function handle(): int
        {
            $marker = (string) $this->option('marker');
            if ($marker !== '') {
                file_put_contents($marker, 'ran');
            }

            return (int) $this->option('code');
        }
    });
}

function registerThrowCommand(): void
{
    app(Kernel::class)->registerCommand(new class extends Command {
        protected $signature = 'lara:throw';

        public function handle(): int
        {
            throw new RuntimeException('boom from a forked command');
        }
    });
}

it('runs a foreground command in a fork, executes its body, and returns exit 0', function () {
    registerMarkCommand();
    $marker = sys_get_temp_dir().'/lara-shell-marker-'.bin2hex(random_bytes(4));

    $code = makeForkingDriver()->run(['lara:mark', '--marker='.$marker], new BufferedOutput());

    expect($code)->toBe(0)
        ->and(is_file($marker))->toBeTrue()          // proves the forked child ran the command body
        ->and(file_get_contents($marker))->toBe('ran');

    @unlink($marker);
    expect(true)->toBeTrue();                          // sentinel: the parent survived the fork
});

it('returns the command exit code from the fork', function () {
    registerMarkCommand();

    $code = makeForkingDriver()->run(['lara:mark', '--code=7'], new BufferedOutput());

    expect($code)->toBe(7);
});

it('survives a command that throws (fork crash isolation)', function () {
    registerThrowCommand();

    $code = makeForkingDriver()->run(['lara:throw'], new BufferedOutput());

    expect($code)->toBeGreaterThan(0);
    expect(true)->toBeTrue();                          // sentinel: parent still running after the crash
});

it('falls back to the local driver when a fake command is unknown but still returns a code', function () {
    $code = makeForkingDriver()->run(['this:command-does-not-exist'], new BufferedOutput());

    expect($code)->toBeGreaterThan(0);                 // unknown command -> non-zero, parent survives
    expect(true)->toBeTrue();
});
