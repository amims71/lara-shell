<?php

namespace Amims71\LaraShell\Features;

use Amims71\LaraShell\Support\CommandCatalog;

class CommandResolver
{
    public function __construct(private CommandCatalog $catalog) {}

    /** Exact/alias → prefix+colon-abbrev → fzf subsequence. Returns canonical name or null. */
    public function resolve(string $token): ?string
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        // Tier 1: exact name or alias.
        $meta = $this->catalog->get($token);
        if ($meta !== null) {
            return $meta->name;
        }

        $names = $this->canonicalNames();

        // Tier 2a: Symfony-style prefix abbreviation (unambiguous).
        $prefixHits = array_values(array_filter(
            $names,
            fn (string $name) => str_starts_with($name, $token)
        ));
        if (count($prefixHits) === 1) {
            return $prefixHits[0];
        }

        // Tier 2b: colon-segment abbreviation, e.g. "m:f" -> "migrate:fresh".
        if (str_contains($token, ':')) {
            $segmentHits = array_values(array_filter(
                $names,
                fn (string $name) => $this->matchesColonSegments($token, $name)
            ));
            if (count($segmentHits) === 1) {
                return $segmentHits[0];
            }
        }

        // Tier 3: fzf subsequence — resolve only if there is a single clear winner.
        $ranked = $this->rank($token, $names);
        if ($ranked === []) {
            return null;
        }

        [$topName, $topScore] = $ranked[0];
        $runnerScore = $ranked[1][1] ?? 0.0;

        // Require a strong, unambiguous top match to auto-resolve; otherwise defer to suggest().
        if ($topScore >= 0.5 && ($topScore - $runnerScore) >= 0.10) {
            return $topName;
        }

        return null;
    }

    /** @return string[] ranked canonical names (for "did you mean" / palette) */
    public function suggest(string $token, int $limit = 5): array
    {
        $token = trim($token);
        if ($token === '' || $limit <= 0) {
            return [];
        }

        $ranked = $this->rank($token, $this->canonicalNames());

        return array_map(
            fn (array $entry) => $entry[0],
            array_slice($ranked, 0, $limit)
        );
    }

    /**
     * fzf-style subsequence score in [0.0, 1.0]; 0.0 = not a subsequence.
     * Consecutive-character runs and matches at word/colon boundaries score higher.
     */
    public function score(string $needle, string $haystack): float
    {
        $needle = strtolower($needle);
        $haystack = strtolower($haystack);

        if ($needle === '') {
            return 0.0;
        }

        $hLen = strlen($haystack);
        $nLen = strlen($needle);
        if ($nLen > $hLen) {
            return 0.0;
        }

        $raw = 0.0;
        $ni = 0;              // index into needle
        $prevMatchIndex = -2; // last matched haystack index (for consecutive detection)

        for ($hi = 0; $hi < $hLen && $ni < $nLen; $hi++) {
            if ($haystack[$hi] !== $needle[$ni]) {
                continue;
            }

            $charScore = 1.0;

            // word/colon boundary bonus: match starts a segment
            $isBoundary = $hi === 0
                || $haystack[$hi - 1] === ':'
                || $haystack[$hi - 1] === ' '
                || $haystack[$hi - 1] === '-'
                || $haystack[$hi - 1] === '_';
            if ($isBoundary) {
                $charScore += 1.5;
            }

            // consecutive-character bonus
            if ($hi === $prevMatchIndex + 1) {
                $charScore += 1.0;
            }

            $raw += $charScore;
            $prevMatchIndex = $hi;
            $ni++;
        }

        // not all needle characters matched → not a subsequence
        if ($ni < $nLen) {
            return 0.0;
        }

        // best achievable raw per matched char is 1 base + 1.5 boundary + 1 consecutive = 3.5
        $maxPerChar = 3.5;
        $normalized = $raw / ($nLen * $maxPerChar);

        // slight reward for a tight match relative to haystack length
        $density = $nLen / $hLen;
        $score = ($normalized * 0.8) + ($density * 0.2);

        return max(0.0, min(1.0, $score));
    }

    /** @return string[] canonical (non-alias) command names */
    private function canonicalNames(): array
    {
        $names = [];
        foreach ($this->catalog->all() as $name => $meta) {
            $names[] = $meta->name;
        }

        return array_values(array_unique($names));
    }

    /**
     * Rank names by score() descending; drops zero-score (non-subsequence) entries.
     *
     * @param  string[]  $names
     * @return array<int,array{0:string,1:float}> [name, score] pairs, best first
     */
    private function rank(string $token, array $names): array
    {
        $scored = [];
        foreach ($names as $name) {
            $s = $this->score($token, $name);
            if ($s > 0.0) {
                $scored[] = [$name, $s];
            }
        }

        usort($scored, function (array $a, array $b): int {
            if ($a[1] === $b[1]) {
                return strcmp($a[0], $b[0]);
            }

            return $b[1] <=> $a[1];
        });

        return $scored;
    }

    /**
     * True when a colon-abbreviated token matches a colon-segmented command name segment-by-segment,
     * each token segment being a prefix of the corresponding command segment.
     * e.g. "m:f" matches "migrate:fresh"; "r:l" matches "route:list".
     */
    private function matchesColonSegments(string $token, string $name): bool
    {
        $tokenSegments = explode(':', strtolower($token));
        $nameSegments = explode(':', strtolower($name));

        if (count($tokenSegments) !== count($nameSegments)) {
            return false;
        }

        foreach ($tokenSegments as $i => $seg) {
            if ($seg === '' || ! str_starts_with($nameSegments[$i], $seg)) {
                return false;
            }
        }

        return true;
    }
}
