<?php

namespace env;

use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\exec;

#[AsTask(description: 'Display environment variables')]
function env(Context $context)
{
    $context = $context->withEnvironment([
        'FOO' => 'toto',
    ]);
    exec('echo \"$FOO\"', context: $context);
}
