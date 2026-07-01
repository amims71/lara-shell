<?php

use Amims71\LaraShell\Support\ArgumentMeta;
use Amims71\LaraShell\Support\CommandCatalog;
use Amims71\LaraShell\Support\CommandMeta;
use Amims71\LaraShell\Support\OptionMeta;
use Illuminate\Contracts\Console\Kernel;

it('exposes readonly option metadata', function () {
    $opt = new OptionMeta('--force', 'f', false, 'Force the operation to run');

    expect($opt->name)->toBe('--force');
    expect($opt->shortcut)->toBe('f');
    expect($opt->acceptsValue)->toBeFalse();
    expect($opt->description)->toBe('Force the operation to run');
});

it('exposes readonly argument metadata', function () {
    $arg = new ArgumentMeta('name', true, false, 'The name of the thing');

    expect($arg->name)->toBe('name');
    expect($arg->required)->toBeTrue();
    expect($arg->isArray)->toBeFalse();
    expect($arg->description)->toBe('The name of the thing');
});

it('exposes readonly command metadata', function () {
    $meta = new CommandMeta(
        'migrate',
        'Run the database migrations',
        ['db:migrate'],
        false,
        'migrate [--force]',
        [new OptionMeta('--force', 'f', false, 'Force the operation to run')],
        [],
    );

    expect($meta->name)->toBe('migrate');
    expect($meta->description)->toBe('Run the database migrations');
    expect($meta->aliases)->toBe(['db:migrate']);
    expect($meta->hidden)->toBeFalse();
    expect($meta->synopsis)->toBe('migrate [--force]');
    expect($meta->options)->toHaveCount(1);
    expect($meta->options[0])->toBeInstanceOf(OptionMeta::class);
    expect($meta->arguments)->toBe([]);
});

it('builds the catalog from the artisan kernel', function () {
    $catalog = new CommandCatalog($this->app->make(Kernel::class));

    $all = $catalog->all();

    expect($all)->toHaveKey('list');
    expect($all)->toHaveKey('migrate');
    expect($all['migrate'])->toBeInstanceOf(CommandMeta::class);
    expect($all['migrate']->name)->toBe('migrate');
    expect($all['migrate']->description)->not->toBe('');
});

it('resolves get() for a canonical name and returns its options', function () {
    $catalog = new CommandCatalog($this->app->make(Kernel::class));

    $meta = $catalog->get('migrate');

    expect($meta)->toBeInstanceOf(CommandMeta::class);

    $optionNames = array_map(fn ($o) => $o->name, $meta->options);
    expect($optionNames)->toContain('--force');
});

it('returns null from get() for an unknown command', function () {
    $catalog = new CommandCatalog($this->app->make(Kernel::class));

    expect($catalog->get('this:does-not-exist'))->toBeNull();
});

it('includes aliases and canonical names in a sorted names() list', function () {
    $catalog = new CommandCatalog($this->app->make(Kernel::class));

    $names = $catalog->names();

    $sorted = $names;
    sort($sorted);
    expect($names)->toBe($sorted);
    expect($names)->toContain('migrate');

    $aliases = [];
    foreach ($catalog->all() as $meta) {
        foreach ($meta->aliases as $alias) {
            $aliases[] = $alias;
        }
    }

    if ($aliases !== []) {
        expect($names)->toContain($aliases[0]);
        expect($catalog->get($aliases[0]))->toBeInstanceOf(CommandMeta::class);
    } else {
        expect(true)->toBeTrue();
    }
});

it('rebuilds the memoized map on refresh()', function () {
    $catalog = new CommandCatalog($this->app->make(Kernel::class));

    $first = $catalog->all();
    expect($catalog->all())->toBe($first);

    $catalog->refresh();

    $second = $catalog->all();
    expect($second)->not->toBe($first);
    expect($second)->toHaveKey('migrate');
    expect(array_keys($second))->toEqualCanonicalizing(array_keys($first));
});
