<?php

namespace Amims71\LaraShell\Features;

use Amims71\LaraShell\Support\CommandCatalog;
use Amims71\LaraShell\Support\CommandMeta;

class Palette
{
    public function __construct(
        private CommandCatalog $catalog,
        private CommandResolver $resolver
    ) {}

    /**
     * @return CommandMeta[] ranked by fuzzy score; empty query → all commands name-sorted.
     */
    public function search(string $query, int $limit = 20): array
    {
        $commands = array_values($this->catalog->all());

        if (trim($query) === '') {
            usort($commands, fn (CommandMeta $a, CommandMeta $b) => strcmp($a->name, $b->name));

            return array_slice($commands, 0, $limit);
        }

        $scored = [];

        foreach ($commands as $meta) {
            $score = $this->scoreCommand($query, $meta);

            if ($score > 0.0) {
                $scored[] = ['meta' => $meta, 'score' => $score];
            }
        }

        usort($scored, function (array $a, array $b): int {
            return $b['score'] <=> $a['score']
                ?: strcmp($a['meta']->name, $b['meta']->name);
        });

        $ranked = array_map(fn (array $row) => $row['meta'], $scored);

        return array_slice($ranked, 0, $limit);
    }

    private function scoreCommand(string $query, CommandMeta $meta): float
    {
        $best = $this->resolver->score($query, $meta->name);

        foreach (preg_split('/\s+/', trim($meta->description)) ?: [] as $word) {
            if ($word === '') {
                continue;
            }

            $wordScore = $this->resolver->score($query, $word);

            if ($wordScore > $best) {
                $best = $wordScore;
            }
        }

        return $best;
    }
}
