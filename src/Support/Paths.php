<?php

namespace Amims71\LaraShell\Support;

class Paths
{
    public function __construct(private string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function configFile(): string
    {
        return $this->basePath.'/.lara-shell.php';
    }

    public function storageDir(): string
    {
        return $this->basePath.'/storage/lara-shell';
    }

    public function logsDir(): string
    {
        return $this->storageDir().'/logs';
    }

    public function jobsFile(): string
    {
        return $this->storageDir().'/jobs.json';
    }

    public function jobLog(string $id): string
    {
        return $this->logsDir().'/'.$id.'.log';
    }

    public function historyFile(): string
    {
        return $this->storageDir().'/history.jsonl';
    }

    public function socketPath(): string
    {
        $hash = substr(hash('sha256', $this->basePath), 0, 8);

        return sys_get_temp_dir().'/lara-shell-'.$hash.'.sock';
    }

    public function pidFile(): string
    {
        return $this->storageDir().'/daemon.pid';
    }

    public function ensureDirectories(): void
    {
        foreach ([$this->storageDir(), $this->logsDir()] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
        }

        // Keep the host app's git clean: ignore everything here except this file.
        $gitignore = $this->storageDir().'/.gitignore';

        if (! is_file($gitignore)) {
            file_put_contents($gitignore, "*\n!.gitignore\n");
        }
    }
}
