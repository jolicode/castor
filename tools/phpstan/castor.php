<?php

namespace castor\qa;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Run PHPStan', aliases: ['phpstan'])]
function phpstan(bool $generateBaseline = false): int
{
    return run(
        [__DIR__ . '/vendor/bin/phpstan', '--configuration=' . \dirname(__DIR__, 2) . '/phpstan.neon', $generateBaseline ? '-b' : ''],
        allowFailure: true,
    )->getExitCode();
}
