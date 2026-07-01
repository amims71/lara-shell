<?php

namespace Amims71\LaraShell;

use Illuminate\Support\ServiceProvider;

class LaraShellServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/lara-shell.php', 'lara-shell');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/lara-shell.php' => $this->app->configPath('lara-shell.php'),
            ], 'lara-shell-config');
        }

        // Task 19 finalizes command registration + singleton bindings.
    }
}
