<?php

use Amims71\LaraShell\Shell\Matchers\ArtisanNameMatcher;
use Amims71\LaraShell\Support\CommandCatalog;
use Amims71\LaraShell\Support\CommandMeta;

/**
 * A CommandCatalog with a fixed name list (subclassing is permitted — no `final`).
 * Only names() is exercised by the matcher, so the kernel dependency is never used.
 */
function catalogWithNames(array $names): CommandCatalog
{
    return new class($names) extends CommandCatalog {
        public function __construct(private array $fakeNames)
        {
        }

        public function all(): array
        {
            return [];
        }

        public function names(): array
        {
            sort($this->fakeNames);

            return $this->fakeNames;
        }

        public function get(string $name): ?CommandMeta
        {
            return null;
        }

        public function refresh(): void {}
    };
}

it('matches command names by prefix on the first token', function () {
    $matcher = new ArtisanNameMatcher(catalogWithNames([
        'migrate', 'migrate:fresh', 'migrate:rollback', 'serve', 'route:list',
    ]));

    $matches = $matcher->getMatches(['mig']);

    sort($matches);
    expect($matches)->toBe(['migrate', 'migrate:fresh', 'migrate:rollback']);
});

it('reports hasMatched true only when there is a single leading token', function () {
    $matcher = new ArtisanNameMatcher(catalogWithNames(['serve']));

    expect($matcher->hasMatched(['ser']))->toBeTrue()
        ->and($matcher->hasMatched([]))->toBeFalse();
});
