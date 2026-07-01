<?php

use Amims71\LaraShell\Features\CommandResolver;
use Amims71\LaraShell\Support\CommandCatalog;

/**
 * score() never touches the catalog, so we back the resolver with a CommandCatalog
 * subclass that exposes no commands. Per correction C2 we do NOT hand-roll the
 * Illuminate console Kernel contract — subclassing the real catalog (C1: non-final)
 * gives a genuine instance with zero framework dependency.
 */
function makeScoreResolver(): CommandResolver
{
    $catalog = new class extends CommandCatalog
    {
        public function __construct() {}

        public function all(): array
        {
            return [];
        }

        public function names(): array
        {
            return [];
        }

        public function get(string $name): ?\Amims71\LaraShell\Support\CommandMeta
        {
            return null;
        }
    };

    return new CommandResolver($catalog);
}

it('returns 0.0 when the needle is not a subsequence of the haystack', function () {
    $resolver = makeScoreResolver();

    expect($resolver->score('xyz', 'migrate'))->toBe(0.0);
    expect($resolver->score('gm', 'migrate'))->toBe(0.0); // out of order
});

it('returns >0 for an exact and for a full subsequence match', function () {
    $resolver = makeScoreResolver();

    expect($resolver->score('migrate', 'migrate'))->toBeGreaterThan(0.0);
    expect($resolver->score('mig', 'migrate'))->toBeGreaterThan(0.0);
});

it('scores a closer/consecutive match higher than a scattered one (monotonicity)', function () {
    $resolver = makeScoreResolver();

    // consecutive prefix beats scattered subsequence over the same haystack
    $consecutive = $resolver->score('mig', 'migrate');
    $scattered = $resolver->score('mrt', 'migrate');

    expect($consecutive)->toBeGreaterThan($scattered);
    expect($scattered)->toBeGreaterThan(0.0);
});

it('rewards colon/word-boundary starts (mf beats interior match)', function () {
    $resolver = makeScoreResolver();

    // 'mf' hits the start of each colon segment of migrate:fresh
    $boundary = $resolver->score('mf', 'migrate:fresh');
    // 'ir' matches interior chars only, no boundary bonus
    $interior = $resolver->score('ir', 'migrate:fresh');

    expect($boundary)->toBeGreaterThan($interior);
    expect($interior)->toBeGreaterThan(0.0);
});

it('is case-insensitive', function () {
    $resolver = makeScoreResolver();

    expect($resolver->score('MIG', 'migrate'))->toBe($resolver->score('mig', 'migrate'));
});

it('clamps the returned score into the inclusive range 0.0..1.0', function () {
    $resolver = makeScoreResolver();

    $score = $resolver->score('migrate', 'migrate');

    expect($score)->toBeGreaterThanOrEqual(0.0);
    expect($score)->toBeLessThanOrEqual(1.0);
});

it('returns empty resolve/suggest against an empty catalog', function () {
    $resolver = makeScoreResolver();

    expect($resolver->resolve('migrate'))->toBeNull();
    expect($resolver->suggest('migrate'))->toBe([]);
});
