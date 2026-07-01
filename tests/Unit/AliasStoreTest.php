<?php

use Amims71\LaraShell\Features\AliasStore;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/lara-shell-alias-'.bin2hex(random_bytes(4));
    mkdir($this->dir, 0700, true);
    $this->file = $this->dir.'/.lara-shell.php';
});

afterEach(function () {
    foreach (glob($this->dir.'/{,.}*', GLOB_BRACE) ?: [] as $f) {
        if (is_file($f)) {
            unlink($f);
        }
    }
    if (is_dir($this->dir)) {
        rmdir($this->dir);
    }
});

it('returns empty arrays when the config file does not exist', function () {
    $store = new AliasStore($this->file);

    expect($store->aliases())->toBe([]);
    expect($store->macros())->toBe([]);
});

it('reads aliases and macros back from an existing file', function () {
    file_put_contents($this->file, <<<'PHP'
    <?php return [
        'aliases' => ['mf' => 'migrate:fresh --seed', 'rl' => 'route:list'],
        'macros' => ['reset' => ['migrate:fresh --seed', 'db:seed']],
    ];
    PHP);

    $store = new AliasStore($this->file);

    expect($store->aliases())->toBe([
        'mf' => 'migrate:fresh --seed',
        'rl' => 'route:list',
    ]);
    expect($store->macros())->toBe([
        'reset' => ['migrate:fresh --seed', 'db:seed'],
    ]);
});

it('persists a set alias so a fresh instance reads it back', function () {
    $store = new AliasStore($this->file);
    $store->setAlias('mf', 'migrate:fresh --seed');

    $fresh = new AliasStore($this->file);

    expect($fresh->aliases())->toBe(['mf' => 'migrate:fresh --seed']);
    expect(is_file($this->file))->toBeTrue();
});

it('writes a valid PHP array file that can be required directly', function () {
    $store = new AliasStore($this->file);
    $store->setAlias('rl', 'route:list');

    $data = require $this->file;

    expect($data)->toBeArray();
    expect($data['aliases'])->toBe(['rl' => 'route:list']);
    expect($data['macros'])->toBe([]);
});

it('removes an alias and persists the removal', function () {
    $store = new AliasStore($this->file);
    $store->setAlias('mf', 'migrate:fresh --seed');
    $store->setAlias('rl', 'route:list');

    $store->removeAlias('mf');

    expect($store->aliases())->toBe(['rl' => 'route:list']);

    $fresh = new AliasStore($this->file);
    expect($fresh->aliases())->toBe(['rl' => 'route:list']);
});

it('reload picks up external changes to the file', function () {
    $store = new AliasStore($this->file);
    expect($store->aliases())->toBe([]);

    file_put_contents($this->file, <<<'PHP'
    <?php return ['aliases' => ['ti' => 'tinker'], 'macros' => []];
    PHP);

    $store->reload();

    expect($store->aliases())->toBe(['ti' => 'tinker']);
});
