<?php

namespace castor\markdown;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Context;
use Symfony\Component\Process\Process;

use function Castor\context;
use function Castor\docker\docker_run;
use function Castor\io;

const DOCKER_IMAGE_NAME = 'davidanson/markdownlint-cli2:v0.18.1';

#[AsTask(description: 'Lint markdown files', aliases: ['lint-markdown'])]
function lint(
    #[AsOption(description: 'Whether to fix some errors')]
    bool $fix = false,
): int {
    io()->title('Linting Markdown documents');

    $command = [
        '--config',
        '/castor/tools/markdown/.markdownlint-cli2.yaml',
    ];

    if ($fix) {
        $command[] = '--fix';
    }

    $exitCode = do_run($command, c: context()->withAllowFailure())->getExitCode();

    io()->newLine();
    io()->note('For more info about rules, see https://github.com/DavidAnson/markdownlint/blob/main/doc/Rules.md');

    return $exitCode;
}

function do_run(array $runCommand, ?Context $c = null): Process
{
    return docker_run(
        DOCKER_IMAGE_NAME,
        $runCommand,
        workDir: '/castor',
        volumes: [
            \sprintf('%s:/castor:cached', realpath(__DIR__ . '/../..')),
        ],
        context: $c,
    );
}
