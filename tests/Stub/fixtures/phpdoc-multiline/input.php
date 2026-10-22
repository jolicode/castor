<?php

namespace Test\Foobar;

use Symfony\Component\Process\Process;

/**
 * Runs the command, and returns the
 * process once it is finished.
 *
 * @param string  $command The command to run, which may be
 *                         written on several lines
 * @param Process $process
 */
function run(string $command, Process $process): Process
{
    return $process;
}
