<?php

use Amims71\LaraShell\Features\AliasLoopException;
use Amims71\LaraShell\Features\AliasStore;
use Amims71\LaraShell\Features\Expander;

function makeExpander(array $aliases, int $maxDepth = 10): Expander
{
    $dir = sys_get_temp_dir().'/lara-shell-expander-'.bin2hex(random_bytes(4));
    mkdir($dir, 0700, true);
    $file = $dir.'/.lara-shell.php';
    file_put_contents($file, '<?php return '.var_export([
        'aliases' => $aliases,
        'macros' => [],
    ], true).";\n");

    return new Expander(new AliasStore($file), $maxDepth);
}

it('expands a simple alias on the first word', function () {
    $expander = makeExpander(['mf' => 'migrate:fresh --seed']);

    $result = $expander->expand('mf', fn (string $name) => false);

    expect($result)->toBe('migrate:fresh --seed');
});

it('leaves a line unchanged when the first word is not an alias', function () {
    $expander = makeExpander(['mf' => 'migrate:fresh --seed']);

    $result = $expander->expand('route:list --json', fn (string $name) => false);

    expect($result)->toBe('route:list --json');
});

it('appends remaining args after the expansion', function () {
    $expander = makeExpander(['mf' => 'migrate:fresh --seed']);

    $result = $expander->expand('mf --step', fn (string $name) => false);

    expect($result)->toBe('migrate:fresh --seed --step');
});

it('does not expand when the first word is a real command (real command wins)', function () {
    $expander = makeExpander(['list' => 'route:list']);

    $result = $expander->expand('list', fn (string $name) => $name === 'list');

    expect($result)->toBe('list');
});

it('expands a chain of aliases (a -> b -> migrate)', function () {
    $expander = makeExpander([
        'a' => 'b --one',
        'b' => 'migrate --two',
    ]);

    $result = $expander->expand('a --three', fn (string $name) => $name === 'migrate');

    expect($result)->toBe('migrate --two --one --three');
});

it('throws on a direct alias cycle (a -> b -> a)', function () {
    $expander = makeExpander([
        'a' => 'b',
        'b' => 'a',
    ]);

    $expander->expand('a', fn (string $name) => false);
})->throws(AliasLoopException::class);

it('throws when a self-referential alias loops', function () {
    $expander = makeExpander(['x' => 'x --forever']);

    $expander->expand('x', fn (string $name) => false);
})->throws(AliasLoopException::class);

it('throws when expansion exceeds the max depth', function () {
    $expander = makeExpander([
        'a' => 'b',
        'b' => 'c',
        'c' => 'd',
    ], maxDepth: 2);

    $expander->expand('a', fn (string $name) => false);
})->throws(AliasLoopException::class);
