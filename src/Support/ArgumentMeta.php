<?php

namespace Amims71\LaraShell\Support;

class ArgumentMeta
{
    public function __construct(
        public readonly string $name,
        public readonly bool $required,
        public readonly bool $isArray,
        public readonly string $description,
    ) {}
}
