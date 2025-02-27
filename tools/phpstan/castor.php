<?php

namespace qa\phpstan;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\exit_code;
use function Castor\io;
use function Castor\run;

#[AsTask(description: 'Run PHPStan', aliases: ['phpstan'])]
function phpstan(bool $generateBaseline = false): int
{
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        install();
    }

    $command = [
        __DIR__ . '/vendor/bin/phpstan', '-v', '--memory-limit=512M',
    ];

    if ($generateBaseline) {
        $command[] = '-b';
    }

    return exit_code($command);
}

#[AsTask(description: 'install dependencies')]
function install(): void
{
    run(['composer', 'install'], context: context()->withWorkingDirectory(__DIR__));
}

#[AsTask(description: 'update dependencies')]
function update(): void
{
    io()->section('Update phpstan dependencies');
    run(['composer', 'update'], context: context()->withWorkingDirectory(__DIR__));
}
