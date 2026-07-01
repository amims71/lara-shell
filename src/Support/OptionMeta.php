<?php

namespace Amims71\LaraShell\Support;

class OptionMeta
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $shortcut,
        public readonly bool $acceptsValue,
        public readonly string $description,
    ) {}
}
