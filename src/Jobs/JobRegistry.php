<?php

namespace Amims71\LaraShell\Jobs;

interface JobRegistry
{
    /** @return Job[] */
    public function all(): array;

    public function find(string $id): ?Job;

    public function put(Job $job): void;

    public function remove(string $id): void;
}
