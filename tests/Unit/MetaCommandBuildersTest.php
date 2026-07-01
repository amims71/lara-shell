<?php

use Amims71\LaraShell\Features\AliasStore;
use Amims71\LaraShell\Features\Palette;
use Amims71\LaraShell\Jobs\Job;
use Amims71\LaraShell\Jobs\JobRegistry;
use Amims71\LaraShell\Shell\Commands\AliasCommand;
use Amims71\LaraShell\Shell\Commands\JobsCommand;
use Amims71\LaraShell\Shell\Commands\KillCommand;
use Amims71\LaraShell\Shell\Commands\PaletteCommand;
use Amims71\LaraShell\Support\CommandMeta;

function registryWith(array $jobs): JobRegistry
{
    return new class($jobs) implements JobRegistry {
        /** @param Job[] $jobs */
        public function __construct(private array $jobs)
        {
        }
        public function all(): array { return $this->jobs; }
        public function find(string $id): ?Job
        {
            foreach ($this->jobs as $job) if ($job->id === $id) return $job;
            return null;
        }
        public function put(Job $job): void {}
        public function remove(string $id): void {}
    };
}

it('builds job rows with id, command, status and uptime', function () {
    $registry = registryWith([
        new Job('aa11', 100, 'serve', 'running', time() - 65, null, '/tmp/aa11.log'),
    ]);

    $rows = JobsCommand::rows($registry);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe('aa11')
        ->and($rows[0]['command'])->toBe('serve')
        ->and($rows[0]['status'])->toBe('running')
        ->and($rows[0]['uptime'])->toContain('1m');
});

it('resolves a pid for a known job id and null for unknown', function () {
    $registry = registryWith([
        new Job('bb22', 200, 'queue:work', 'running', time(), null, '/tmp/bb22.log'),
    ]);

    expect(KillCommand::resolvePid($registry, 'bb22'))->toBe(200)
        ->and(KillCommand::resolvePid($registry, 'zzzz'))->toBeNull();
});

it('adds, lists and removes an alias mutating the store', function () {
    $store = new class extends AliasStore {
        public array $data = [];
        public function __construct()
        {
        }
        public function aliases(): array { return $this->data; }
        public function macros(): array { return []; }
        public function setAlias(string $name, string $expansion): void { $this->data[$name] = $expansion; }
        public function removeAlias(string $name): void { unset($this->data[$name]); }
        public function reload(): void {}
    };

    expect(AliasCommand::apply($store, ['add', 'mf', 'migrate:fresh']))->toContain('mf');
    expect($store->aliases())->toBe(['mf' => 'migrate:fresh']);

    $listed = AliasCommand::apply($store, ['list']);
    expect($listed)->toContain('mf')->and($listed)->toContain('migrate:fresh');

    AliasCommand::apply($store, ['rm', 'mf']);
    expect($store->aliases())->toBe([]);
});

it('builds palette rows from a Palette search', function () {
    $palette = new class extends Palette {
        public function __construct()
        {
        }
        public function search(string $query, int $limit = 20): array
        {
            return [new CommandMeta('migrate', 'Run the database migrations', [], false, 'migrate [--force]', [], [])];
        }
    };

    $rows = PaletteCommand::rows($palette, 'mig');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('migrate')
        ->and($rows[0]['description'])->toBe('Run the database migrations');
});
