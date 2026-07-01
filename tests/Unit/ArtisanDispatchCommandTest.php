<?php

use Amims71\LaraShell\Drivers\Driver;
use Amims71\LaraShell\Features\CommandResolver;
use Amims71\LaraShell\Features\GuardLevel;
use Amims71\LaraShell\Features\SafetyGuard;
use Amims71\LaraShell\Jobs\Job;
use Amims71\LaraShell\Jobs\JobRegistry;
use Amims71\LaraShell\Jobs\LongRunning;
use Amims71\LaraShell\Shell\ArtisanDispatchCommand;
use Amims71\LaraShell\Support\CommandCatalog;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A Driver double that records how it was invoked, so execute()-level pass-through
 * (argv + trailing "&" → background) can be asserted.
 */
function recordingDriver(): Driver
{
    return new class implements Driver {
        public array $ranArgv = [];

        public array $backgroundedArgv = [];

        public function run(array $argv, OutputInterface $output): int
        {
            $this->ranArgv = $argv;

            return 0;
        }

        public function background(array $argv): Job
        {
            $this->backgroundedArgv = $argv;

            return new Job('aa', 1, implode(' ', $argv), 'running', time(), null, '/tmp/x.log');
        }

        public function jobs(): JobRegistry
        {
            throw new RuntimeException('n/a');
        }

        public function reload(): void {}
    };
}

function makeDispatch(string $name, array $longRunning, array $tokens = []): array
{
    $driver = recordingDriver();
    $resolver = new CommandResolver(new CommandCatalog(app(Kernel::class)));
    $guard = new SafetyGuard(app(), ['environments' => ['production'], 'block' => [], 'confirm' => ['migrate']]);

    $cmd = new ArtisanDispatchCommand($driver, $resolver, $guard, new LongRunning($longRunning), $name, $tokens);

    return [$cmd, $driver];
}

function invokeExecute(ArtisanDispatchCommand $cmd): int
{
    $input = new ArrayInput([]);
    $input->bind($cmd->getDefinition());

    $method = new ReflectionMethod($cmd, 'execute');
    $method->setAccessible(true);

    return $method->invoke($cmd, $input, new NullOutput());
}

it('flags a guarded command as Confirm in production', function () {
    $this->app['env'] = 'production';
    [$cmd] = makeDispatch('migrate', ['serve']);

    $decision = $cmd->decide(['migrate']);

    expect($decision['name'])->toBe('migrate')
        ->and($decision['level'])->toBe(GuardLevel::Confirm)
        ->and($decision['background'])->toBeFalse();
});

it('backgrounds a long-running command by allowlist', function () {
    [$cmd] = makeDispatch('serve', ['serve']);

    $decision = $cmd->decide(['serve']);

    expect($decision['background'])->toBeTrue()
        ->and($decision['argv'])->toBe(['serve']);
});

it('backgrounds when a trailing ampersand is present and strips it', function () {
    [$cmd] = makeDispatch('queue:work', []);

    $decision = $cmd->decide(['queue:work', '&']);

    expect($decision['background'])->toBeTrue()
        ->and($decision['argv'])->toBe(['queue:work']);
});

it('allows a plain fast command to run in the foreground', function () {
    [$cmd] = makeDispatch('route:list', ['serve']);

    $decision = $cmd->decide(['route:list']);

    expect($decision['level'])->toBe(GuardLevel::Allow)
        ->and($decision['background'])->toBeFalse();
});

it('passes the injected argv straight through to the driver on a foreground run', function () {
    [$cmd, $driver] = makeDispatch('route:list', [], ['route:list', '--json', '--path=/api']);

    $exit = invokeExecute($cmd);

    expect($exit)->toBe(0)
        ->and($driver->ranArgv)->toBe(['route:list', '--json', '--path=/api'])
        ->and($driver->backgroundedArgv)->toBe([]);
});

it('routes a trailing ampersand to the driver as a background job', function () {
    [$cmd, $driver] = makeDispatch('route:list', [], ['route:list', '--json', '&']);

    $exit = invokeExecute($cmd);

    expect($exit)->toBe(0)
        ->and($driver->backgroundedArgv)->toBe(['route:list', '--json'])
        ->and($driver->ranArgv)->toBe([]);
});

it('does not duplicate the command name when given a single bare token', function () {
    [$cmd, $driver] = makeDispatch('serve', ['serve'], ['serve']);

    $exit = invokeExecute($cmd);

    expect($exit)->toBe(0)
        ->and($driver->backgroundedArgv)->toBe(['serve'])
        ->and($driver->ranArgv)->toBe([]);
});
