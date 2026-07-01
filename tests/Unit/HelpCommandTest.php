<?php

use Amims71\LaraShell\Shell\Commands\HelpCommand;
use Amims71\LaraShell\Support\ArgumentMeta;
use Amims71\LaraShell\Support\CommandCatalog;
use Amims71\LaraShell\Support\CommandMeta;
use Amims71\LaraShell\Support\OptionMeta;
use Illuminate\Contracts\Console\Kernel;

it('registers as "help" with h/about/guide aliases', function () {
    $command = new HelpCommand(new CommandCatalog(app(Kernel::class)));

    expect($command->getName())->toBe('help')
        ->and($command->getAliases())->toContain('h')
        ->and($command->getAliases())->toContain('about')
        ->and($command->getAliases())->toContain('guide');
});

it('exposes an ordered guide covering the core capabilities', function () {
    $sections = HelpCommand::sections();

    expect($sections)->not->toBeEmpty()
        ->and(array_keys($sections))->toBe(range(0, count($sections) - 1));

    $titles = array_map(fn ($s) => $s['title'], $sections);
    $blob = json_encode($sections);

    expect($titles)->toContain('Artisan commands')
        ->and($blob)->toContain('php artisan serve')
        ->and($blob)->toContain('jobs')
        ->and($blob)->toContain('alias add')
        ->and($blob)->toContain('@reset')
        ->and($blob)->toContain('safety');
});

it('renders per-command usage from command metadata', function () {
    $meta = new CommandMeta(
        'migrate',
        'Run the database migrations',
        [],
        false,
        'migrate [--force] [--] [<name>]',
        [new OptionMeta('--force', 'f', false, 'Force the operation to run in production')],
        [new ArgumentMeta('name', false, false, 'The migration name')],
    );

    $blob = implode("\n", HelpCommand::commandHelp($meta));

    expect($blob)->toContain('migrate')
        ->and($blob)->toContain('Run the database migrations')
        ->and($blob)->toContain('Usage:')
        ->and($blob)->toContain('migrate [--force]')
        ->and($blob)->toContain('--force')
        ->and($blob)->toContain('(-f)')
        ->and($blob)->toContain('name');
});
