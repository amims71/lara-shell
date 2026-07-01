<?php

namespace Amims71\LaraShell\Shell\Matchers;

use Amims71\LaraShell\Support\CommandCatalog;
use Psy\TabCompletion\Matcher\AbstractMatcher;

class ArtisanNameMatcher extends AbstractMatcher
{
    public function __construct(private CommandCatalog $catalog)
    {
    }

    public function hasMatched(array $tokens): bool
    {
        return count($tokens) === 1 && is_string($tokens[0]);
    }

    public function getMatches(array $tokens, array $info = []): array
    {
        $prefix = (string) ($tokens[0] ?? '');

        return array_values(array_filter(
            $this->catalog->names(),
            fn (string $name): bool => AbstractMatcher::startsWith($prefix, $name)
        ));
    }
}
