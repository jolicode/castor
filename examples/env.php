<?php

namespace env;

use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\run;

#[AsTask(description: 'Display environment variables')]
function env(Context $context)
{
    $context = $context->withEnvironment([
        'FOO' => 'toto',
    ]);
    run('echo \"$FOO\"', context: $context);
}
