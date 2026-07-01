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
use Amims71\LaraShell\Jobs\FileJobRegistry;
use Amims71\LaraShell\Jobs\JobRegistry;
use Amims71\LaraShell\Jobs\LongRunning;
use Amims71\LaraShell\Jobs\ProcessTree;
use Amims71\LaraShell\Shell\ArtisanShell;
use Amims71\LaraShell\Support\CommandCatalog;
use Amims71\LaraShell\Support\Paths;

it('registers the shell command with terminal and repl aliases', function () {
    $command = $this->app->make(ShellCommand::class);

    expect($command->getName())->toBe('shell')
        ->and($command->getAliases())->toContain('terminal')
        ->and($command->getAliases())->toContain('repl');
});

it('registers the shell command on the artisan kernel with its aliases', function () {
    $all = $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->all();

    expect($all)->toHaveKey('shell');

    $registered = $all['shell'];
    expect($registered->getAliases())->toContain('terminal')
        ->and($registered->getAliases())->toContain('repl');
});

it('binds every Plan-1 service as a resolvable singleton', function () {
    $bindings = [
        Paths::class,
        CommandCatalog::class,
        CommandResolver::class,
        AliasStore::class,
        Expander::class,
        SafetyGuard::class,
        Palette::class,
        ProcessTree::class,
        LongRunning::class,
        DriverFactory::class,
        JobRegistry::class,
        LocalDriver::class,
        Driver::class,
    ];

    foreach ($bindings as $abstract) {
        $first = $this->app->make($abstract);
        $second = $this->app->make($abstract);

        expect($first)->toBeInstanceOf($abstract)
            ->and($first)->toBe($second);
    }
});

it('binds JobRegistry to FileJobRegistry and Driver to the platform driver', function () {
    $expected = DriverFactory::supportsForking() ? ForkingDriver::class : LocalDriver::class;

    expect($this->app->make(JobRegistry::class))->toBeInstanceOf(FileJobRegistry::class)
        ->and($this->app->make(Driver::class))->toBeInstanceOf(Driver::class)
        ->and($this->app->make(Driver::class))->toBeInstanceOf($expected);
});

it('builds a fully wired ArtisanShell with meta-commands without starting it', function () {
    /** @var ShellCommand $command */
    $command = $this->app->make(ShellCommand::class);

    $meta = $command->metaCommands(
        $this->app->make(Driver::class),
        $this->app->make(CommandCatalog::class),
        $this->app->make(JobRegistry::class),
        $this->app->make(ProcessTree::class),
        $this->app->make(AliasStore::class),
        $this->app->make(CommandResolver::class),
        $this->app->make(Palette::class),
    );

    $shell = $command->buildShell(
        $this->app->make(Paths::class),
        $this->app->make(Driver::class),
        $this->app->make(CommandResolver::class),
        $this->app->make(CommandCatalog::class),
        $this->app->make(SafetyGuard::class),
        $this->app->make(LongRunning::class),
        $this->app->make(AliasStore::class),
        $this->app->make(Expander::class),
        $meta,
    );

    expect($shell)->toBeInstanceOf(ArtisanShell::class)
        ->and($shell->classifyInput('jobs'))->toBe('meta')
        ->and($shell->classifyInput('palette'))->toBe('meta')
        ->and($shell->classifyInput('reload'))->toBe('meta');
});
