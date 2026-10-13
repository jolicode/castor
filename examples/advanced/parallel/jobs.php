<?php

namespace parallel;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\parallel;
use function Castor\run;

#[AsTask(description: 'Runs named jobs in parallel, and organizes their output')]
function jobs(bool $fail = false): void
{
    // Each named argument is a job: its output is grouped under its name
    $results = parallel(
        lint: static fn () => run('sleep 0.3; echo "Linting a.php"; sleep 0.6; echo "Linting b.php"'),
        test: static function () use ($fail): int {
            io()->writeln('Running the test suite');
            run('sleep 0.6; echo "Test 1: OK"; sleep 0.6; echo "Test 2: OK"');
            if ($fail) {
                run('echo "Test 3: KO" >&2; exit 1');
            }

            return 2;
        },
        build: static fn () => run('sleep 1.6; echo "Assets compiled"'),
    );

    io()->success("{$results['test']} tests passed.");
}
