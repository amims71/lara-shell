<?php

use Amims71\LaraShell\Support\Paths;

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/lara-shell-paths-'.bin2hex(random_bytes(4));
    mkdir($this->base, 0777, true);
    $this->paths = new Paths($this->base);
});

afterEach(function () {
    $storage = $this->base.'/storage/lara-shell';
    foreach (['logs', ''] as $sub) {
        $dir = rtrim($storage.'/'.$sub, '/');
        if (is_dir($dir)) {
            foreach (glob($dir.'/*') ?: [] as $f) {
                if (is_file($f)) {
                    unlink($f);
                }
            }
        }
    }
    @unlink($this->base.'/storage/lara-shell/.gitignore');
    @rmdir($this->base.'/storage/lara-shell/logs');
    @rmdir($this->base.'/storage/lara-shell');
    @rmdir($this->base.'/storage');
    @rmdir($this->base);
});

it('composes config file path at the base root', function () {
    expect($this->paths->configFile())->toBe($this->base.'/.lara-shell.php');
});

it('composes storage and logs directories', function () {
    expect($this->paths->storageDir())->toBe($this->base.'/storage/lara-shell');
    expect($this->paths->logsDir())->toBe($this->base.'/storage/lara-shell/logs');
});

it('composes jobs, history and pid file paths under storage', function () {
    expect($this->paths->jobsFile())->toBe($this->base.'/storage/lara-shell/jobs.json');
    expect($this->paths->historyFile())->toBe($this->base.'/storage/lara-shell/history.jsonl');
    expect($this->paths->pidFile())->toBe($this->base.'/storage/lara-shell/daemon.pid');
});

it('composes a per-job log path under the logs directory', function () {
    expect($this->paths->jobLog('a1b2c3d4'))->toBe($this->base.'/storage/lara-shell/logs/a1b2c3d4.log');
});

it('composes a stable, base-scoped socket path under the system temp dir', function () {
    $expected = sys_get_temp_dir().'/lara-shell-'.substr(hash('sha256', $this->base), 0, 8).'.sock';
    expect($this->paths->socketPath())->toBe($expected);
    expect($this->paths->socketPath())->toBe($this->paths->socketPath());
});

it('produces different socket paths for different base paths', function () {
    $other = new Paths($this->base.'-other');
    expect($other->socketPath())->not->toBe($this->paths->socketPath());
});

it('creates the storage and logs directories when missing', function () {
    expect(is_dir($this->paths->storageDir()))->toBeFalse();
    expect(is_dir($this->paths->logsDir()))->toBeFalse();

    $this->paths->ensureDirectories();

    expect(is_dir($this->paths->storageDir()))->toBeTrue();
    expect(is_dir($this->paths->logsDir()))->toBeTrue();
});

it('is idempotent when directories already exist', function () {
    $this->paths->ensureDirectories();
    $this->paths->ensureDirectories();

    expect(is_dir($this->paths->logsDir()))->toBeTrue();
});

it('drops a self-ignoring .gitignore into the storage directory', function () {
    $this->paths->ensureDirectories();

    $gitignore = $this->paths->storageDir().'/.gitignore';

    expect(is_file($gitignore))->toBeTrue()
        ->and(file_get_contents($gitignore))->toBe("*\n!.gitignore\n");
});
