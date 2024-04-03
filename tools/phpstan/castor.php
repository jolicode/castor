<?php

namespace qa\phpstan;

use Castor\Attribute\AsTask;

use function Castor\exit_code;
use function Castor\run;

#[AsTask(description: 'Run PHPStan', aliases: ['phpstan'])]
function phpstan(bool $generateBaseline = false): int
{
    $command = [
        __DIR__ . '/vendor/bin/phpstan',
    ];

    if ($generateBaseline) {
        $command[] = '-b';
    }

    return exit_code($command);
}

#[AsTask(description: 'install dependencies')]
function install(): void
{
    run(['composer', 'install'], workingDirectory: __DIR__);
}

#[AsTask(description: 'update dependencies')]
function update(): void
{
    run(['composer', 'update'], workingDirectory: __DIR__);
    run(['composer', 'bump'], workingDirectory: __DIR__);
}
