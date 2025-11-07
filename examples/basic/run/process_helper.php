<?php

namespace run;

use Castor\Attribute\AsTask;
use Symfony\Component\Console\Helper\ProcessHelper;

use function Castor\app;
use function Castor\output;

#[AsTask(description: 'Run a sub-process and display information about it, with ProcessHelper')]
function process_helper(): void
{
    if (!output()->isVeryVerbose()) {
        output()->writeln('Re-run with -vv, -vvv to see the output of the process.');
    }

    /** @var ProcessHelper */
    $helper = app()->getHelperSet()->get('process');
    $helper->run(output(), ['ls', '-alh']);
}
