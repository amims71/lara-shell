<?php

namespace Amims71\LaraShell\Jobs;

class LongRunning
{
    /** @param string[] $allowlist name/prefix patterns (e.g. 'queue:*') */
    public function __construct(private array $allowlist) {}

    public function matches(string $command): bool
    {
        foreach ($this->allowlist as $pattern) {
            if ($command === $pattern) {
                return true;
            }

            if (fnmatch($pattern, $command)) {
                return true;
            }
        }

        return false;
    }
}
