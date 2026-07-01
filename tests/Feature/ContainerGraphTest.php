<?php

use Amims71\LaraShell\Console\ShellCommand;
use Amims71\LaraShell\Drivers\Driver;
use Amims71\LaraShell\Drivers\DriverFactory;
use Amims71\LaraShell\Drivers\ForkingDriver;
use Amims71\LaraShell\Drivers\LocalDriver;
use Amims71\LaraShell\Features\AliasStore;
use Amims71\LaraShell\Features\CommandResolver;
use Amims71\LaraShell\Features\Expander;
use Amims71\LaraShell\Features\Palette;
use Amims71\LaraShell\Features\SafetyGuard;
use Amims71\LaraShell\Jobs\JobRegistry;
use Amims71\LaraShell\Jobs\LongRunning;
use Amims71\LaraShell\Jobs\ProcessTree;
use Amims71\LaraShell\Shell\ArtisanShell;
use Amims71\LaraShell\Support\CommandCatalog;
use Amims71\LaraShell\Support\Paths;

/** Build the shell exactly as ShellCommand::handle would, straight from the container. */
function containerShell($app): ArtisanShell
{
    /** @var ShellCommand $command */
    $command = $app->make(ShellCommand::class);

    $meta = $command->metaCommands(
        $app->make(Driver::class),
        $app->make(CommandCatalog::class),
        $app->make(JobRegistry::class),
        $app->make(ProcessTree::class),
        $app->make(AliasStore::class),
        $app->make(CommandResolver::class),
        $app->make(Palette::class),
    );

    return $command->buildShell(
        $app->make(Paths::class),
        $app->make(Driver::class),
        $app->make(CommandResolver::class),
        $app->make(CommandCatalog::class),
        $app->make(SafetyGuard::class),
        $app->make(LongRunning::class),
        $app->make(AliasStore::class),
        $app->make(Expander::class),
        $meta,
    );
}

it('resolves the full execution graph from the container', function () {
    $driver = $this->app->make(Driver::class);
    $registry = $this->app->make(JobRegistry::class);
    $shell = containerShell($this->app);

    expect($driver)->toBeInstanceOf(Driver::class)
        ->and($registry)->toBeInstanceOf(JobRegistry::class)
        ->and($shell)->toBeInstanceOf(ArtisanShell::class);
});

it('DriverFactory make returns the platform driver (warm-fork on Unix)', function () {
    $driver = $this->app->make(DriverFactory::class)->make();

    expect($driver)->toBeInstanceOf(
        DriverFactory::supportsForking() ? ForkingDriver::class : LocalDriver::class
    );
});

it('classifies representative lines end-to-end through the resolved shell', function () {
    $shell = containerShell($this->app);

    expect($shell->classifyInput(';User::count()'))->toBe('php')
        ->and($shell->classifyInput('migrate'))->toBe('artisan')
        ->and($shell->classifyInput('route:list'))->toBe('artisan')
        ->and($shell->classifyInput('@deploy'))->toBe('macro')
        ->and($shell->classifyInput('jobs'))->toBe('meta')
        ->and($shell->classifyInput('kill'))->toBe('meta')
        ->and($shell->classifyInput('palette'))->toBe('meta')
        ->and($shell->classifyInput('reload'))->toBe('meta')
        ->and($shell->classifyInput('nonsense123'))->toBe('php')
        ->and($shell->classifyInput(''))->toBe('php');
});

it('routes a resolved artisan line to a runnable command via getCommand', function () {
    $shell = containerShell($this->app);

    expect($shell->hasCommand('migrate'))->toBeTrue()
        ->and($shell->hasCommand('jobs'))->toBeTrue()
        ->and($shell->hasCommand('@deploy'))->toBeTrue()
        ->and($shell->hasCommand(';User::count()'))->toBeFalse();
});
