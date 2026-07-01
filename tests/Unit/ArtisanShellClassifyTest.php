<?php

use Amims71\LaraShell\Drivers\Driver;
use Amims71\LaraShell\Features\AliasLoopException;
use Amims71\LaraShell\Features\AliasStore;
use Amims71\LaraShell\Features\CommandResolver;
use Amims71\LaraShell\Features\Expander;
use Amims71\LaraShell\Features\SafetyGuard;
use Amims71\LaraShell\Jobs\Job;
use Amims71\LaraShell\Jobs\JobRegistry;
use Amims71\LaraShell\Jobs\LongRunning;
use Amims71\LaraShell\Shell\ArtisanShell;
use Amims71\LaraShell\Shell\MacroCommand;
use Amims71\LaraShell\Support\CommandCatalog;
use Illuminate\Contracts\Console\Kernel;
use Psy\Configuration;
use Psy\VersionUpdater\Checker;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/** A no-op Driver double (implementing the interface is fine; only the app contract is off-limits). */
function shellDriver(): Driver
{
    return new class implements Driver {
        public function run(array $argv, OutputInterface $output): int
        {
            return 0;
        }

        public function background(array $argv): Job
        {
            return new Job('aa', 1, implode(' ', $argv), 'running', time(), null, '/tmp/x.log');
        }

        public function jobs(): JobRegistry
        {
            throw new RuntimeException('n/a');
        }

        public function reload(): void {}
    };
}

/**
 * Build an ArtisanShell backed by real container-resolved services. Aliases/macros are
 * written to a real temp .lara-shell.php so AliasStore is a genuine instance.
 */
function buildShell(array $aliases = [], array $macros = []): ArtisanShell
{
    $file = sys_get_temp_dir().'/lara-shell-classify-'.bin2hex(random_bytes(4)).'.php';
    file_put_contents($file, '<?php return '.var_export(['aliases' => $aliases, 'macros' => $macros], true).';');

    $catalog = new CommandCatalog(app(Kernel::class));
    $resolver = new CommandResolver($catalog);
    $guard = new SafetyGuard(app(), ['environments' => ['production'], 'block' => [], 'confirm' => []]);
    $aliasStore = new AliasStore($file);

    $config = new Configuration(['configFile' => null, 'usePcntl' => false]);
    $config->setUpdateCheck(Checker::NEVER);

    $shell = new ArtisanShell(
        $config, shellDriver(), $resolver, $catalog, $guard,
        new LongRunning([]), $aliasStore, new Expander($aliasStore, 10)
    );

    register_shutdown_function(fn () => @unlink($file));

    return $shell;
}

it('classifies a leading semicolon as php', function () {
    expect(buildShell()->classifyInput(';User::count()'))->toBe('php');
});

it('classifies a registered artisan name as artisan', function () {
    expect(buildShell()->classifyInput('serve'))->toBe('artisan');
});

it('classifies an @-prefixed word as a macro', function () {
    $shell = buildShell(macros: ['reset' => ['migrate:fresh']]);
    expect($shell->classifyInput('@reset'))->toBe('macro');
});

it('classifies a meta-command as meta', function () {
    expect(buildShell()->classifyInput('jobs'))->toBe('meta');
});

it('classifies the help command and its aliases as meta', function () {
    $shell = buildShell();

    expect($shell->classifyInput('help'))->toBe('meta')
        ->and($shell->classifyInput('h'))->toBe('meta')
        ->and($shell->classifyInput('about'))->toBe('meta')
        ->and($shell->classifyInput('guide'))->toBe('meta');
});

it('resolves help to our HelpCommand without shadowing PsySH built-in help', function () {
    $shell = buildShell();

    $get = new ReflectionMethod($shell, 'getCommand');
    $get->setAccessible(true);

    expect($get->invoke($shell, 'help'))->toBeInstanceOf(\Amims71\LaraShell\Shell\Commands\HelpCommand::class)
        ->and($get->invoke($shell, 'h'))->toBeInstanceOf(\Amims71\LaraShell\Shell\Commands\HelpCommand::class);

    // PsySH's own help must stay registered so its internal error-help path keeps working.
    expect($shell->get('help'))->toBeInstanceOf(\Psy\Command\HelpCommand::class);
});

it('classifies unknown input as php', function () {
    expect(buildShell()->classifyInput('nonsense123'))->toBe('php');
});

it('classifies an alias that expands to a real command as artisan', function () {
    $shell = buildShell(aliases: ['mf' => 'migrate:fresh']);
    expect($shell->classifyInput('mf'))->toBe('artisan');
});

it('returns a runnable MacroCommand for @name input', function () {
    $shell = buildShell(macros: ['reset' => ['route:list']]);

    $executed = [];
    $shell->setLineExecutor(function (string $line) use (&$executed): int {
        $executed[] = $line;

        return 0;
    });

    $method = new ReflectionMethod($shell, 'getCommand');
    $method->setAccessible(true);
    $command = $method->invoke($shell, '@reset');

    expect($command)->toBeInstanceOf(MacroCommand::class);

    $input = new ArrayInput([]);
    $input->bind($command->getDefinition());
    $exec = new ReflectionMethod($command, 'execute');
    $exec->setAccessible(true);
    $code = $exec->invoke($command, $input, new NullOutput());

    expect($code)->toBe(0)
        ->and($executed)->toBe(['route:list']);
});

it('runs each macro step through the injected line executor', function () {
    $shell = buildShell(macros: ['reset' => ['route:list', 'migrate:fresh']]);

    $executed = [];
    $shell->setLineExecutor(function (string $line) use (&$executed): int {
        $executed[] = $line;

        return 0;
    });

    $shell->runMacro('reset');

    expect($executed)->toBe(['route:list', 'migrate:fresh']);
});

it('throws AliasLoopException when a macro references itself', function () {
    $shell = buildShell(macros: ['loop' => ['@loop']]);

    $shell->runMacro('loop');
})->throws(AliasLoopException::class);
