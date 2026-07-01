<?php

use Amims71\LaraShell\Features\CommandResolver;
use Amims71\LaraShell\Support\CommandCatalog;
use Illuminate\Contracts\Console\Kernel;

function commandResolver(): CommandResolver
{
    $catalog = new CommandCatalog(app(Kernel::class));

    return new CommandResolver($catalog);
}

it('tier 1: resolves an exact canonical command name', function () {
    expect(commandResolver()->resolve('migrate'))->toBe('migrate');
    expect(commandResolver()->resolve('route:list'))->toBe('route:list');
});

it('tier 1: resolve() defers to catalog->get() for exact hits', function () {
    $catalog = new CommandCatalog(app(Kernel::class));
    $resolver = new CommandResolver($catalog);

    expect($resolver->resolve('migrate'))->toBe('migrate');
    expect($catalog->get('migrate'))->not->toBeNull();
});

it('tier 2: resolves an unambiguous prefix abbreviation', function () {
    $resolver = commandResolver();

    expect($resolver->resolve('route:l'))->toBe('route:list');
});

it('tier 2: resolves colon-segment abbreviation r:l -> route:list when unambiguous', function () {
    $resolver = commandResolver();

    expect($resolver->resolve('r:l'))->toBe('route:list');
});

it('tier 2: ambiguous prefix returns null but suggest() is non-empty', function () {
    $resolver = commandResolver();

    // "m" alone is a prefix of many commands (make:*, migrate, migrate:*),
    // so it must not resolve, yet must produce ranked suggestions.
    expect($resolver->resolve('m'))->toBeNull();
    expect($resolver->suggest('m'))->not->toBeEmpty();
});

it('tier 3: mfs resolves or suggests migrate:fresh via fzf subsequence', function () {
    $resolver = commandResolver();

    $resolved = $resolver->resolve('mfs');
    $suggestions = $resolver->suggest('mfs', 8);

    expect($resolved === 'migrate:fresh' || in_array('migrate:fresh', $suggestions, true))->toBeTrue();
});

it('tier 3: typo mgrate resolves to migrate via subsequence', function () {
    $resolver = commandResolver();

    $resolved = $resolver->resolve('mgrate');
    $suggestions = $resolver->suggest('mgrate', 8);

    expect($resolved === 'migrate' || in_array('migrate', $suggestions, true))->toBeTrue();
});

it('returns null and empty suggestions when nothing is a subsequence', function () {
    $resolver = commandResolver();

    expect($resolver->resolve('zzqxwv'))->toBeNull();
    expect($resolver->suggest('zzqxwv'))->toBeEmpty();
});

it('suggest() honours the limit', function () {
    $resolver = commandResolver();

    expect(count($resolver->suggest('m', 3)))->toBeLessThanOrEqual(3);
});
