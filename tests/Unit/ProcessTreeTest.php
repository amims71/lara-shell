<?php

use Amims71\LaraShell\Jobs\ProcessTree;

/**
 * A ProcessTree whose ps output is a fixture, so descendants() is testable
 * without touching real processes.
 */
function fixtureTree(string $psOutput): ProcessTree
{
    return new class ($psOutput) extends ProcessTree {
        public function __construct(private string $fixture) {}

        protected function psOutput(): string
        {
            return $this->fixture;
        }
    };
}

it('parses `ps -eo pid=,ppid=` output into a pid=>ppid map', function () {
    $output = <<<PS
    1     0
  100     1
  200   100
  201   100
  300   200
PS;

    $tree = new ProcessTree();

    expect($tree->parsePsOutput($output))->toBe([
        1 => 0,
        100 => 1,
        200 => 100,
        201 => 100,
        300 => 200,
    ]);
});

it('ignores blank and malformed ps lines', function () {
    $output = "  100   1\n\n   \nnot-a-number here\n  200   100\n";

    $tree = new ProcessTree();

    expect($tree->parsePsOutput($output))->toBe([
        100 => 1,
        200 => 100,
    ]);
});

it('walks the full descendant tree from a pid (self first)', function () {
    // 200 is the job; 300 is a child web server; 400,401 are grandchildren of 300.
    $tree = fixtureTree(<<<PS
  1     0
100     1
200   100
300   200
400   300
401   300
500   999
PS);

    $descendants = $tree->descendants(200);

    expect($descendants[0])->toBe(200)
        ->and($descendants)->toEqualCanonicalizing([200, 300, 400, 401])
        ->and($descendants)->not->toContain(100)
        ->and($descendants)->not->toContain(500);
});

it('returns just the pid when it has no children', function () {
    $tree = fixtureTree("100 1\n200 100\n");

    expect($tree->descendants(200))->toBe([200]);
});
