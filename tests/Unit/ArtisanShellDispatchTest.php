<?php

use Amims71\LaraShell\Drivers\Driver;
use Amims71\LaraShell\Features\AliasStore;
use Amims71\LaraShell\Features\CommandResolver;
use Amims71\LaraShell\Features\Expander;
use Amims71\LaraShell\Features\SafetyGuard;
use Amims71\LaraShell\Jobs\Job;
use Amims71\LaraShell\Jobs\JobRegistry;
use Amims71\LaraShell\Jobs\LongRunning;
use Amims71\LaraShell\Shell\ArtisanShell;
use Amims71\LaraShell\Support\CommandCatalog;
use Illuminate\Contracts\Console\Kernel;
use Psy\Configuration;
use Psy\VersionUpdater\Checker;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/** A Driver double that records the argv it was handed. */
function dispatchRecordingDriver(): Driver
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

function buildDispatchShell(Driver $driver, array $longRunning = [], array $aliases = []): ArtisanShell
{
    $file = sys_get_temp_dir().'/lara-shell-dispatch-'.bin2hex(random_bytes(4)).'.php';
    file_put_contents($file, '<?php return '.var_export(['aliases' => $aliases, 'macros' => []], true).';');
    register_shutdown_function(fn () => @unlink($file));

    $catalog = new CommandCatalog(app(Kernel::class));
    $resolver = new CommandResolver($catalog);
    $guard = new SafetyGuard(app(), ['environments' => ['production'], 'block' => [], 'confirm' => []]);
    $aliasStore = new AliasStore($file);

    $config = new Configuration(['configFile' => null, 'usePcntl' => false]);
    $config->setUpdateCheck(Checker::NEVER);

    return new ArtisanShell(
        $config, $driver, $resolver, $catalog, $guard,
        new LongRunning($longRunning), $aliasStore, new Expander($aliasStore, 10)
    );
}

/**
 * Run the shell's dispatch for a typed line exactly as PsySH would: resolve the command via
 * getCommand(), then run it with a StringInput of the FULL line — which (like PsySH) binds the
 * command word into the command's own input. The old code duplicated it; the fix ignores it.
 */
function dispatchLine(ArtisanShell $shell, string $line): void
{
    $get = new ReflectionMethod($shell, 'getCommand');
    $get->setAccessible(true);
    $command = $get->invoke($shell, $line);

    $command->run(new StringInput($line), new NullOutput());
}

it('dispatches a bare command without duplicating its name (regression: "serve serve")', function () {
    $driver = dispatchRecordingDriver();
    $shell = buildDispatchShell($driver, longRunning: ['serve']);

    dispatchLine($shell, 'serve');

    expect($driver->backgroundedArgv)->toBe(['serve'])
        ->and($driver->ranArgv)->toBe([]);
});

it('preserves options through the real dispatch path', function () {
    $driver = dispatchRecordingDriver();
    $shell = buildDispatchShell($driver);

    dispatchLine($shell, 'route:list --json');

    expect($driver->ranArgv)->toBe(['route:list', '--json'])
        ->and($driver->backgroundedArgv)->toBe([]);
});

it('backgrounds on a trailing ampersand through the real dispatch path', function () {
    $driver = dispatchRecordingDriver();
    $shell = buildDispatchShell($driver);

    dispatchLine($shell, 'route:list &');

    expect($driver->backgroundedArgv)->toBe(['route:list'])
        ->and($driver->ranArgv)->toBe([]);
});

it('expands an alias and dispatches the expansion', function () {
    $driver = dispatchRecordingDriver();
    $shell = buildDispatchShell($driver, aliases: ['qqzz' => 'route:list']);

    dispatchLine($shell, 'qqzz --json');

    expect($driver->ranArgv)->toBe(['route:list', '--json']);
});
