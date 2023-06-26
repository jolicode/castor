<?php

namespace castor\qa;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Fix CS', aliases: ['cs'])]
function cs(
    #[AsOption(description: 'Only shows which files would have been modified.')]
    bool $dryRyn,
): int {
    $command = [__DIR__ . '/vendor/bin/php-cs-fixer', 'fix', '--config', \dirname(__DIR__, 2) . '/.php-cs-fixer.php'];

    if ($dryRyn) {
        $command[] = '--dry-run';
    }

    return run($command, allowFailure: true)->getExitCode();
}
