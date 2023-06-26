<?php

namespace castor\qa;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Run PHPStan', aliases: ['phpstan'])]
function phpstan(): int
{
    return run(
        [__DIR__ . '/vendor/bin/phpstan', '--configuration=' . \dirname(__DIR__, 2) . '/phpstan.neon'],
        allowFailure: true,
    )->getExitCode();
}
