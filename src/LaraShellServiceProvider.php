<?php

namespace Amims71\LaraShell;

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
use Amims71\LaraShell\Support\CommandCatalog;
use Amims71\LaraShell\Support\Paths;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class LaraShellServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lara-shell.php', 'lara-shell');

        $this->app->singleton(Paths::class, fn (Application $app) => new Paths($app->basePath()));

        $this->app->singleton(CommandCatalog::class, fn (Application $app) => new CommandCatalog($app->make(Kernel::class)));

        $this->app->singleton(CommandResolver::class, fn (Application $app) => new CommandResolver($app->make(CommandCatalog::class)));

        $this->app->singleton(AliasStore::class, fn (Application $app) => new AliasStore($app->make(Paths::class)->configFile()));

        $this->app->singleton(Expander::class, fn (Application $app) => new Expander($app->make(AliasStore::class)));

        $this->app->singleton(SafetyGuard::class, fn (Application $app) => new SafetyGuard($app, (array) config('lara-shell.guard', [])));

        $this->app->singleton(Palette::class, fn (Application $app) => new Palette($app->make(CommandCatalog::class), $app->make(CommandResolver::class)));

        $this->app->singleton(ProcessTree::class, fn () => new ProcessTree());

        $this->app->singleton(JobRegistry::class, fn (Application $app) => new FileJobRegistry(
            $app->make(Paths::class)->jobsFile(),
            $app->make(ProcessTree::class)
        ));

        $this->app->singleton(LongRunning::class, fn () => new LongRunning((array) config('lara-shell.long_running', [])));

        $this->app->singleton(DriverFactory::class, fn (Application $app) => new DriverFactory($app));

        $this->app->singleton(LocalDriver::class, fn (Application $app) => new LocalDriver(
            PHP_BINARY,
            $app->basePath('artisan'),
            $app->make(JobRegistry::class),
            $app->make(Paths::class),
        ));

        $this->app->singleton(ForkingDriver::class, fn (Application $app) => new ForkingDriver(
            $app->make(LocalDriver::class),
            $app->make(Kernel::class),
            PHP_BINARY,
            $app->basePath('artisan'),
        ));

        $this->app->singleton(Driver::class, fn (Application $app) => $app->make(DriverFactory::class)->make());

        $this->commands([ShellCommand::class]);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/lara-shell.php' => $this->app->configPath('lara-shell.php'),
            ], 'lara-shell-config');
        }
    }
}
