<?php

it('boots the service provider and merges the package config', function () {
    expect(config('lara-shell.command.name'))->toBe('shell');
});

it('exposes the command aliases from config', function () {
    expect(config('lara-shell.command.aliases'))->toBe(['terminal', 'repl']);
});

it('exposes the default long-running allowlist', function () {
    expect(config('lara-shell.long_running'))->toContain('serve')
        ->and(config('lara-shell.long_running'))->toContain('queue:work');
});

it('exposes guard defaults', function () {
    expect(config('lara-shell.guard.environments'))->toBe(['production'])
        ->and(config('lara-shell.guard.confirm'))->toContain('migrate');
});
