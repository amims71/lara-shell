<?php

use Amims71\LaraShell\Jobs\FileJobRegistry;
use Amims71\LaraShell\Jobs\Job;
use Amims71\LaraShell\Jobs\ProcessTree;

/**
 * A ProcessTree whose isAlive() answers come from an injected map.
 */
function fakeTree(array $aliveByPid): ProcessTree
{
    return new class($aliveByPid) extends ProcessTree
    {
        /** @param  array<int,bool>  $aliveByPid */
        public function __construct(private array $aliveByPid) {}

        public function isAlive(int $pid): bool
        {
            return $this->aliveByPid[$pid] ?? false;
        }
    };
}

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/lara-shell-registry-'.bin2hex(random_bytes(4));
    mkdir($this->dir, 0700, true);
    $this->jobsFile = $this->dir.'/jobs.json';
});

afterEach(function () {
    if (is_file($this->jobsFile)) {
        unlink($this->jobsFile);
    }
    if (is_dir($this->dir)) {
        rmdir($this->dir);
    }
});

it('puts, finds, and lists jobs (round-trip)', function () {
    $registry = new FileJobRegistry($this->jobsFile, fakeTree([100 => true, 200 => true]));

    $a = new Job('aaaa1111', 100, 'serve', 'running', 1_700_000_000, null, '/x/a.log');
    $b = new Job('bbbb2222', 200, 'queue:work', 'running', 1_700_000_010, null, '/x/b.log');

    $registry->put($a);
    $registry->put($b);

    expect($registry->find('aaaa1111'))->not->toBeNull()
        ->and($registry->find('aaaa1111')->command)->toBe('serve')
        ->and($registry->find('bbbb2222')->pid)->toBe(200)
        ->and($registry->find('missing'))->toBeNull();

    $all = $registry->all();

    expect($all)->toHaveCount(2)
        ->and(collect($all)->pluck('id')->all())->toEqualCanonicalizing(['aaaa1111', 'bbbb2222']);
});

it('removes a job', function () {
    $registry = new FileJobRegistry($this->jobsFile, fakeTree([100 => true, 200 => true]));

    $registry->put(new Job('aaaa1111', 100, 'serve', 'running', 1_700_000_000, null, '/x/a.log'));
    $registry->put(new Job('bbbb2222', 200, 'queue:work', 'running', 1_700_000_010, null, '/x/b.log'));

    $registry->remove('aaaa1111');

    expect($registry->find('aaaa1111'))->toBeNull()
        ->and($registry->all())->toHaveCount(1)
        ->and($registry->find('bbbb2222'))->not->toBeNull();
});

it('marks a job with a dead pid as exited and persists the change', function () {
    // pid 100 alive, pid 200 dead.
    $registry = new FileJobRegistry($this->jobsFile, fakeTree([100 => true, 200 => false]));

    $registry->put(new Job('aaaa1111', 100, 'serve', 'running', 1_700_000_000, null, '/x/a.log'));
    $registry->put(new Job('bbbb2222', 200, 'queue:work', 'running', 1_700_000_010, null, '/x/b.log'));

    $all = collect($registry->all())->keyBy('id');

    expect($all['aaaa1111']->status)->toBe('running')
        ->and($all['bbbb2222']->status)->toBe('exited');

    // Change was persisted: a fresh registry (over the same file) sees 'exited'
    // even though the pid is now reported alive.
    $fresh = new FileJobRegistry($this->jobsFile, fakeTree([100 => true, 200 => true]));
    $persisted = collect($fresh->all())->keyBy('id');

    expect($persisted['bbbb2222']->status)->toBe('exited');
});

it('writes the store as valid json on disk', function () {
    $registry = new FileJobRegistry($this->jobsFile, fakeTree([100 => true]));

    $registry->put(new Job('aaaa1111', 100, 'serve', 'running', 1_700_000_000, null, '/x/a.log'));

    expect(is_file($this->jobsFile))->toBeTrue();

    $decoded = json_decode(file_get_contents($this->jobsFile), true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded)->toBeArray()
        ->and($decoded)->toHaveCount(1)
        ->and($decoded[0]['id'])->toBe('aaaa1111')
        ->and($decoded[0]['command'])->toBe('serve');
});
