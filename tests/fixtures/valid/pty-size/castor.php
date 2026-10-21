<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;

#[AsTask]
function size(): void
{
    run(['stty', 'size']);
}

#[AsTask]
function size_shell(): void
{
    run('stty size');
}

#[AsTask]
function command_line(): void
{
    io()->writeln('Command line: ' . run(['true'])->getCommandLine());
}

#[AsTask]
function exit_code(): void
{
    io()->writeln('Exit code: ' . run(['sh', '-c', 'exit 3'], context: context()->withAllowFailure())->getExitCode());
}

#[AsTask]
function failure(): void
{
    run(['false']);
}
