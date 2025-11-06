<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Context;

use function Castor\run;

#[AsContext(name: 'run')]
function create_run_context(): Context
{
    $blankContext = new Context();
    $production = (bool) trim(run('echo $PRODUCTION', context: $blankContext->withQuiet())->getOutput());
    $foo = trim(run('echo $FOO', context: $blankContext->withQuiet())->getOutput()) ?: 'no defined';

    return new Context([
        'name' => 'run',
        'production' => (bool) $production,
        'foo' => $foo,
    ]);
}
