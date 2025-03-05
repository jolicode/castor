<?php

namespace qa;

use Castor\Attribute\AsTask;
use function Castor\context;
use function Castor\PHPQa\phpstan;
use function Castor\PHPQa\php_cs_fixer;

#[AsTask(description: 'Run PHPStan', name: 'phpstan', aliases: ['phpstan'])]
function qa_phpstan(bool $generateBaseline = false): int
{
    $args = ['analyze', context()->workingDirectory . '/src'];

    if ($generateBaseline) {
        $args[] = '-b';
    }

    return phpstan(arguments: $args, version: '2.1.32')->getExitCode();
}

#[AsTask(description: 'Fix CS', name: 'cs', aliases: ['cs'])]
function qa_phpcsfixer(bool $dryRun = false): int
{
    $args = null;

    if ($dryRun) {
        $args = ['fix', '--dry-run'];
    }

    return php_cs_fixer(arguments: $args, version: '3.90.0')->getExitCode();
}
