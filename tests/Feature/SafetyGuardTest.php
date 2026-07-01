<?php

use Amims71\LaraShell\Features\GuardLevel;
use Amims71\LaraShell\Features\SafetyGuard;
use Illuminate\Contracts\Foundation\Application;

function makeGuard(array $overrides = []): SafetyGuard
{
    /** @var Application $app */
    $app = app();

    $config = array_merge([
        'environments' => ['production'],
        'block'        => ['db:wipe'],
        'confirm'      => ['migrate', 'migrate:fresh', 'db:seed'],
    ], $overrides);

    return new SafetyGuard($app, $config);
}

it('confirms guarded commands in a guarded environment', function () {
    app()['env'] = 'production';

    $guard = makeGuard(['block' => []]);

    expect($guard->isGuardedEnvironment())->toBeTrue();
    expect($guard->classify('migrate'))->toBe(GuardLevel::Confirm);
    expect($guard->classify('migrate:fresh'))->toBe(GuardLevel::Confirm);
    expect($guard->classify('db:seed'))->toBe(GuardLevel::Confirm);
});

it('allows unknown commands even in a guarded environment', function () {
    app()['env'] = 'production';

    $guard = makeGuard(['block' => []]);

    expect($guard->classify('route:list'))->toBe(GuardLevel::Allow);
    expect($guard->classify('inspire'))->toBe(GuardLevel::Allow);
});

it('forces at-least-confirm when --force or -f is present', function () {
    app()['env'] = 'production';

    $guard = makeGuard(['block' => [], 'confirm' => []]);

    expect($guard->classify('optimize', ['optimize', '--force']))->toBe(GuardLevel::Confirm);
    expect($guard->classify('optimize', ['optimize', '-f']))->toBe(GuardLevel::Confirm);
    expect($guard->classify('optimize', ['optimize']))->toBe(GuardLevel::Allow);
});

it('blocks blocked commands in a guarded environment', function () {
    app()['env'] = 'production';

    $guard = makeGuard();

    expect($guard->classify('db:wipe'))->toBe(GuardLevel::Block);
});

it('allows everything outside a guarded environment', function () {
    app()['env'] = 'local';

    $guard = makeGuard();

    expect($guard->isGuardedEnvironment())->toBeFalse();
    expect($guard->classify('db:wipe'))->toBe(GuardLevel::Allow);
    expect($guard->classify('migrate'))->toBe(GuardLevel::Allow);
    expect($guard->classify('migrate', ['migrate', '--force']))->toBe(GuardLevel::Allow);
});
