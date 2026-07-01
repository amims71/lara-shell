<?php

use Amims71\LaraShell\Drivers\LocalDriver;
use Amims71\LaraShell\Jobs\FileJobRegistry;
use Amims71\LaraShell\Jobs\Job;
use Amims71\LaraShell\Jobs\ProcessTree;
use Amims71\LaraShell\Support\Paths;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/lara-shell-localdriver-'.bin2hex(random_bytes(4));
    mkdir($this->base, 0700, true);

    $this->paths = new Paths($this->base);
    $this->paths->ensureDirectories();

    $this->registry = new FileJobRegistry($this->paths->jobsFile(), new ProcessTree());
    $this->spawnedPids = [];
});

afterEach(function () {
    foreach ($this->spawnedPids as $pid) {
        if (function_exists('posix_kill')) {
            @posix_kill((int) $pid, defined('SIGKILL') ? SIGKILL : 9);
        } else {
            @exec('kill -9 '.(int) $pid.' 2>/dev/null');
        }
    }

    if (is_dir($this->base)) {
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($rii as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($this->base);
    }
});

it('runs a foreground command, streams output, and returns its exit code', function () {
    $script = $this->base.'/echo-argv.php';
    file_put_contents($script, <<<'PHP'
<?php
fwrite(STDOUT, 'ARGS:'.implode(',', array_slice($argv, 1))."\n");
exit(0);
PHP);

    $driver = new LocalDriver(PHP_BINARY, $script, $this->registry, $this->paths);

    $output = new BufferedOutput();
    $code = $driver->run(['route:list', '--json'], $output);

    expect($code)->toBe(0)
        ->and($output->fetch())->toContain('ARGS:route:list,--json');
});

it('returns the non-zero exit code of a failing foreground command', function () {
    $script = $this->base.'/fail.php';
    file_put_contents($script, <<<'PHP'
<?php
fwrite(STDERR, "boom\n");
exit(7);
PHP);

    $driver = new LocalDriver(PHP_BINARY, $script, $this->registry, $this->paths);

    $output = new BufferedOutput();
    $code = $driver->run(['broken'], $output);

    expect($code)->toBe(7)
        ->and($output->fetch())->toContain('boom');
});

it('backgrounds a command detached, registering a live job with a log path', function () {
    $script = $this->base.'/sleeper.php';
    file_put_contents($script, <<<'PHP'
<?php
fwrite(STDOUT, "started\n");
$deadline = time() + 30;
while (time() < $deadline) {
    usleep(100000);
}
exit(0);
PHP);

    $driver = new LocalDriver(PHP_BINARY, $script, $this->registry, $this->paths);

    $job = $driver->background(['queue:work']);
    $this->spawnedPids[] = $job->pid;

    expect($job)->toBeInstanceOf(Job::class)
        ->and($job->id)->toMatch('/^[0-9a-f]{8}$/')
        ->and($job->pid)->toBeGreaterThan(0)
        ->and($job->status)->toBe('running')
        ->and($job->command)->toContain('queue:work')
        ->and($job->exitCode)->toBeNull()
        ->and($job->logPath)->toBe($this->paths->jobLog($job->id));

    if (function_exists('posix_kill')) {
        expect(posix_kill($job->pid, 0))->toBeTrue();
    }

    $found = $this->registry->find($job->id);
    expect($found)->not->toBeNull()
        ->and($found->pid)->toBe($job->pid)
        ->and($found->logPath)->toBe($job->logPath);
});

it('streams background output into the per-job log file without an active PHP pump', function () {
    $script = $this->base.'/logger.php';
    file_put_contents($script, <<<'PHP'
<?php
fwrite(STDOUT, "hello-from-child\n");
$deadline = time() + 30;
while (time() < $deadline) {
    usleep(100000);
}
exit(0);
PHP);

    $driver = new LocalDriver(PHP_BINARY, $script, $this->registry, $this->paths);

    $job = $driver->background(['serve']);
    $this->spawnedPids[] = $job->pid;

    // The driver holds no reference to the child; the OS-level redirect must keep filling the log.
    unset($driver);

    $log = $job->logPath;
    $deadline = microtime(true) + 5.0;
    $contents = '';
    while (microtime(true) < $deadline) {
        clearstatcache(true, $log);
        if (is_file($log)) {
            $contents = (string) file_get_contents($log);
            if (str_contains($contents, 'hello-from-child')) {
                break;
            }
        }
        usleep(100000);
    }

    expect($contents)->toContain('hello-from-child');
});

it('exposes the injected registry via jobs()', function () {
    $driver = new LocalDriver(PHP_BINARY, $this->base.'/noop.php', $this->registry, $this->paths);

    expect($driver->jobs())->toBe($this->registry);
});

it('reload() is a harmless no-op', function () {
    $driver = new LocalDriver(PHP_BINARY, $this->base.'/noop.php', $this->registry, $this->paths);

    $driver->reload();

    expect($driver->jobs())->toBe($this->registry);
});
