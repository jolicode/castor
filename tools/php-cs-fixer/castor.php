<?php

namespace qa\cs;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\exit_code;
use function Castor\run;

#[AsTask(description: 'Fix CS', aliases: ['cs'])]
function cs(bool $dryRun = false): int
{
    $command = [
        __DIR__ . '/vendor/bin/php-cs-fixer',
        'fix',
    ];

    if ($dryRun) {
        $command[] = '--dry-run';
    }

    return exit_code($command);
}

#[AsTask(description: 'install dependencies')]
function install(): void
{
    run(['composer', 'install'], context: context()->withWorkingDirectory(__DIR__));
}

#[AsTask(description: 'Update dependencies')]
function update(): void
{
    run(['composer', 'update'], context: context()->withWorkingDirectory(__DIR__));
    run(['composer', 'bump'], context: context()->withWorkingDirectory(__DIR__));
}
