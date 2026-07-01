<?php

use Amims71\LaraShell\Features\CommandResolver;
use Amims71\LaraShell\Features\Palette;
use Amims71\LaraShell\Support\CommandCatalog;
use Amims71\LaraShell\Support\CommandMeta;

function makePalette(): Palette
{
    $catalog = app(CommandCatalog::class);
    $resolver = app(CommandResolver::class);

    return new Palette($catalog, $resolver);
}

it('ranks migrate commands first when searching "migrate"', function () {
    $results = makePalette()->search('migrate');

    expect($results)->not->toBeEmpty();
    expect($results[0])->toBeInstanceOf(CommandMeta::class);
    expect($results[0]->name)->toStartWith('migrate');

    $names = array_map(fn (CommandMeta $m) => $m->name, $results);
    expect($names)->toContain('migrate');
});

it('returns all commands name-sorted for an empty query', function () {
    $palette = makePalette();

    $all = $palette->search('', 10000);
    $names = array_map(fn (CommandMeta $m) => $m->name, $all);
    $sorted = $names;
    sort($sorted, SORT_STRING);

    expect($names)->toBe($sorted);
    expect($names)->toContain('migrate');
    expect($names)->toContain('route:list');
});

it('respects the limit for empty and non-empty queries', function () {
    $palette = makePalette();

    expect($palette->search('', 3))->toHaveCount(3);
    expect(count($palette->search('migrate', 2)))->toBeLessThanOrEqual(2);
});
