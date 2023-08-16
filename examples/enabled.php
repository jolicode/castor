<?php

namespace enabled;

use Castor\Attribute\AsTask;

#[AsTask(description: 'Say hello, but only in production', enabled: "var('production') == true")]
function hello(): void
{
    echo "Hello world!\n";
}
