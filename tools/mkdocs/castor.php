<?php

namespace castor\mkdocs;

use Castor\Attribute\AsTask;
use Castor\Context;
use Symfony\Component\Process\Process;

use function Castor\context;
use function Castor\exit_code;
use function Castor\fs;
use function Castor\io;
use function Castor\log;
use function Castor\run;

#[AsTask(description: 'Build mkdocs docker image')]
function docker_build(): int
{
    return exit_code(\sprintf(
        'docker build -t %s %s',
        get_image_name(),
        __DIR__,
    ));
}

#[AsTask(description: 'Build documentation')]
function build(): void
{
    io()->title('Building MkDocs documentation');

    docker_run('mkdocs build');

    $installerPath = __DIR__ . '/../../installer/bash-installer';

    if (fs()->exists($installerPath)) {
        fs()->copy($installerPath, __DIR__ . '/site/install');
    } else {
        io()->error(\sprintf('Bash installer file not found in %s', $installerPath));
    }
}

#[AsTask(description: 'Serve documentation and watches for changes')]
function serve(): void
{
    io()->title('Building and watching MkDocs documentation');

    docker_run('mkdocs serve');
}

function docker_run(string $command, ?Context $c = null): Process
{
    $c ??= context();

    $process = run(\sprintf(
        'docker image inspect %s',
        get_image_name(),
    ), context: context()->withAllowFailure(true)->withQuiet(true));

    if (false === $process->isSuccessful()) {
        throw new \LogicException(\sprintf('Unable to find %s image. Did you forget to run castor mkdocs:docker-build ?', get_image_name()));
    }

    $userId = posix_geteuid();
    $groupId = posix_getegid();

    if ($userId > 256000) {
        $userId = 1000;
        $groupId = 1000;
    }

    if (0 === $userId) {
        log('Running as root? Fallback to fake user id.', 'warning');
        $userId = 1000;
        $groupId = 1000;
    }

    $volumes = [
        \sprintf('-v %s:/mkdocs:cached', realpath(__DIR__)),
        \sprintf('-v %s:/mkdocs/CHANGELOG.md:cached', realpath(__DIR__) . '/../../CHANGELOG.md'),
        \sprintf('-v %s:/mkdocs/doc:cached', realpath(__DIR__ . '/../../doc')),
    ];

    return run(\sprintf(
        'docker run --init --rm %s-t --network=host --user %s:%s %s %s %s',
        $c->quiet || false === $c->tty && false === $c->pty ? '' : '-i ',
        $userId,
        $groupId,
        implode(' ', $volumes),
        get_image_name(),
        $command,
    ), context: $c);
}

function get_image_name()
{
    return 'castor-mkdocs';
}
