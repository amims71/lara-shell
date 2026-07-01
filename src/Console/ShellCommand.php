<?php

namespace Amims71\LaraShell\Console;

use Amims71\LaraShell\Drivers\Driver;
use Amims71\LaraShell\Features\AliasStore;
use Amims71\LaraShell\Features\CommandResolver;
use Amims71\LaraShell\Features\Expander;
use Amims71\LaraShell\Features\Palette;
use Amims71\LaraShell\Features\SafetyGuard;
use Amims71\LaraShell\Jobs\JobRegistry;
use Amims71\LaraShell\Jobs\LongRunning;
use Amims71\LaraShell\Jobs\ProcessTree;
use Amims71\LaraShell\Shell\ArtisanShell;
use Amims71\LaraShell\Shell\Commands\AliasCommand;
use Amims71\LaraShell\Shell\Commands\JobsCommand;
use Amims71\LaraShell\Shell\Commands\KillCommand;
use Amims71\LaraShell\Shell\Commands\LogsCommand;
use Amims71\LaraShell\Shell\Commands\HelpCommand;
use Amims71\LaraShell\Shell\Commands\PaletteCommand;
use Amims71\LaraShell\Shell\Commands\ReloadCommand;
use Amims71\LaraShell\Support\CommandCatalog;
use Amims71\LaraShell\Support\Paths;
use Illuminate\Console\Command;
use Psy\Command\Command as PsyCommand;
use Psy\Configuration;
use Psy\VersionUpdater\Checker;

class ShellCommand extends Command
{
    protected $signature = 'shell';

    protected $description = 'Open an interactive artisan command shell';

    public function __construct()
    {
        parent::__construct();

        $this->setAliases(['terminal', 'repl']);
    }

    public function handle(
        Paths $paths,
        Driver $driver,
        CommandResolver $resolver,
        CommandCatalog $catalog,
        SafetyGuard $guard,
        LongRunning $longRunning,
        AliasStore $aliasStore,
        Expander $expander,
        Palette $palette,
        JobRegistry $registry,
        ProcessTree $tree
    ): int {
        $paths->ensureDirectories();

        $shell = $this->buildShell(
            $paths, $driver, $resolver, $catalog, $guard, $longRunning, $aliasStore, $expander,
            $this->metaCommands($driver, $catalog, $registry, $tree, $aliasStore, $resolver, $palette),
        );

        $this->line('<info>lara-shell</info> · type <comment>help</comment> for a guide, <comment>palette</comment> (or ?) to search, or any artisan command · <comment>exit</comment> to quit');

        return $shell->run();
    }

    /**
     * Assemble the fully wired ArtisanShell without starting the interactive loop.
     *
     * @param  PsyCommand[]  $metaCommands
     */
    public function buildShell(
        Paths $paths,
        Driver $driver,
        CommandResolver $resolver,
        CommandCatalog $catalog,
        SafetyGuard $guard,
        LongRunning $longRunning,
        AliasStore $aliasStore,
        Expander $expander,
        array $metaCommands
    ): ArtisanShell {
        $config = new Configuration(['configFile' => null, 'usePcntl' => false]);
        $config->setHistoryFile($paths->historyFile());
        $config->setUpdateCheck(Checker::NEVER);
        $config->setPrompt('artisan> ');

        $shell = new ArtisanShell(
            $config, $driver, $resolver, $catalog, $guard, $longRunning, $aliasStore, $expander
        );

        $shell->registerMetaCommands($metaCommands);
        $shell->setup();

        return $shell;
    }

    /**
     * Build the shell's meta-commands from their resolved dependencies (correction C6).
     *
     * @return PsyCommand[]
     */
    public function metaCommands(
        Driver $driver,
        CommandCatalog $catalog,
        JobRegistry $registry,
        ProcessTree $tree,
        AliasStore $aliasStore,
        CommandResolver $resolver,
        Palette $palette
    ): array {
        return [
            new ReloadCommand($driver, $catalog),
            new JobsCommand($registry),
            new KillCommand($registry, $tree),
            new LogsCommand($registry),
            new AliasCommand($aliasStore, $resolver),
            new PaletteCommand($palette),
            new HelpCommand($catalog),
        ];
    }
}
