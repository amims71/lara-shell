<?php

use Amims71\LaraShell\Jobs\Job;

it('round-trips through toArray and fromArray', function () {
    $job = new Job(
        id: 'a1b2c3d4',
        pid: 4242,
        command: 'serve --port=8000',
        status: 'running',
        startedAt: 1_700_000_000,
        exitCode: null,
        logPath: '/tmp/lara-shell/logs/a1b2c3d4.log',
    );

    $array = $job->toArray();

    expect($array)->toBe([
        'id' => 'a1b2c3d4',
        'pid' => 4242,
        'command' => 'serve --port=8000',
        'status' => 'running',
        'startedAt' => 1_700_000_000,
        'exitCode' => null,
        'logPath' => '/tmp/lara-shell/logs/a1b2c3d4.log',
    ]);

    $restored = Job::fromArray($array);

    expect($restored)->toBeInstanceOf(Job::class)
        ->and($restored->id)->toBe('a1b2c3d4')
        ->and($restored->pid)->toBe(4242)
        ->and($restored->command)->toBe('serve --port=8000')
        ->and($restored->status)->toBe('running')
        ->and($restored->startedAt)->toBe(1_700_000_000)
        ->and($restored->exitCode)->toBeNull()
        ->and($restored->logPath)->toBe('/tmp/lara-shell/logs/a1b2c3d4.log');
});

it('preserves an integer exit code and exited status', function () {
    $job = Job::fromArray([
        'id' => 'ffff0000',
        'pid' => 99,
        'command' => 'migrate',
        'status' => 'exited',
        'startedAt' => 1_700_000_500,
        'exitCode' => 0,
        'logPath' => '/tmp/lara-shell/logs/ffff0000.log',
    ]);

    expect($job->status)->toBe('exited')
        ->and($job->exitCode)->toBe(0)
        ->and($job->toArray()['exitCode'])->toBe(0);
});
