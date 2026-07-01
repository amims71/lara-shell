<?php

use Amims71\LaraShell\Jobs\LongRunning;

it('matches an exact command name in the allowlist', function () {
    $lr = new LongRunning(['serve', 'queue:work', 'horizon']);

    expect($lr->matches('serve'))->toBeTrue()
        ->and($lr->matches('horizon'))->toBeTrue();
});

it('does not match a command absent from the allowlist', function () {
    $lr = new LongRunning(['serve', 'queue:work']);

    expect($lr->matches('migrate'))->toBeFalse()
        ->and($lr->matches('schedule:run'))->toBeFalse();
});

it('matches via an fnmatch glob pattern', function () {
    $lr = new LongRunning(['serve', 'queue:*']);

    expect($lr->matches('queue:work'))->toBeTrue()
        ->and($lr->matches('queue:listen'))->toBeTrue()
        ->and($lr->matches('queue:restart'))->toBeTrue()
        ->and($lr->matches('serve'))->toBeTrue()
        ->and($lr->matches('migrate'))->toBeFalse();
});

it('does not glob-match across the colon boundary unintentionally', function () {
    $lr = new LongRunning(['queue:*']);

    // 'queue' alone is not 'queue:something', so it must not match.
    expect($lr->matches('queue'))->toBeFalse();
});
