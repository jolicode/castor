<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\exit_code;
use function Castor\io;
use function Castor\run;

// Like `docker run -it`: the terminal is in raw mode, so only the process gets
// the CTRL+C, and it exits with the code 130
#[AsTask]
function terminal(): void
{
    run('exit 130', context: context()->withTty()->withAllowFailure());

    io()->writeln('Next step');
}

#[AsTask]
function terminal_exit_code(): void
{
    exit_code('exit 130', context: context()->withTty());

    io()->writeln('Next step');
}

#[AsTask]
function terminal_trapped(): void
{
    run('exit 130', context: context()->withTty()->withTrappedSignals()->withAllowFailure());

    io()->writeln('Next step');
}

#[AsTask]
function no_terminal(): void
{
    run('exit 130', context: context()->withPty(false)->withAllowFailure());

    io()->writeln('Next step');
}

#[AsTask]
function signaled(): void
{
    run('kill -INT $$', context: context()->withPty(false)->withAllowFailure());

    io()->writeln('Next step');
}

// Like a check catching any failure to go on with the next one
#[AsTask]
function caught(): void
{
    try {
        run('kill -INT $$', context: context()->withPty(false));
    } catch (\Throwable) {
        io()->writeln('Caught');
    }

    io()->writeln('Next step');
}

#[AsTask(onSignals: [2 => 'onSigInt'])]
function signaled_with_handler(): void
{
    run('kill -INT $$', context: context()->withPty(false)->withAllowFailure());

    io()->writeln('Next step');
}

function onSigInt(int $signal): false
{
    io()->writeln('SIGINT received by the task');

    return false;
}

#[AsTask]
function castor_signaled(string $signal): void
{
    $pid = posix_getpid();

    run("(sleep 0.5; kill -{$signal} {$pid}) & exec sleep 10", context: context()->withPty(false)->withAllowFailure());

    io()->writeln('Next step');
}
