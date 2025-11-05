<?php

namespace Castor\docker;

use Castor\Context;
use Symfony\Component\Process\Process;

use function Castor\context;
use function Castor\log;
use function Castor\run;

function docker_run(string $imageName, array $runCommand, ?string $workDir = null, array $volumes = [], array $environment = [], ?Context $context = null): Process
{
    $context ??= context();

    $command = [
        'docker',
        'run',
        '--init',
        '--rm',
        '-t',
        '--network=host',
    ];

    if (!$context->quiet && false !== $context->tty && false !== $context->pty) {
        $command[] = '-i';
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

    $command[] = '--user';
    $command[] = \sprintf('%s:%s', $userId, $groupId);

    if (null !== $workDir) {
        $command[] = '-w';
        $command[] = $workDir;
    }

    foreach ($volumes as $volume) {
        $command[] = '-v';
        $command[] = $volume;
    }

    foreach ($environment as $key => $value) {
        $command[] = '-e';
        $command[] = "{$key}={$value}";
    }

    $command[] = $imageName;
    $command = array_merge($command, $runCommand);

    return run($command, context: $context);
}
