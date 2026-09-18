<?php

// Mimics PHPStan, Rector or composer/xdebug-handler: restarts itself by
// re-executing the PHP binary with its own command line, and must then run
// itself again with its own arguments, not Castor.
if (!getenv('RESTARTED')) {
    // The static build embeds PHP: there is no PHP binary to restart with
    if (!\PHP_BINARY) {
        echo "restart skipped\n";
    } else {
        $process = proc_open(
            [\PHP_BINARY, '-d', 'memory_limit=512M', ...$_SERVER['argv']],
            [1 => \STDOUT, 2 => \STDERR],
            $pipes,
            env_vars: ['RESTARTED' => '1'] + getenv(),
        );

        exit(proc_close($process));
    }
}

echo 'arguments: ', implode(' ', \array_slice($_SERVER['argv'], 1)), "\n";

if (getenv('RESTARTED')) {
    echo 'memory_limit: ', \ini_get('memory_limit'), "\n";
}
