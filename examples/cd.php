<?php

namespace cd;

use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\run;

#[AsTask(description: 'Changes directory')]
function directory(Context $context)
{
    run(['pwd'], context: $context);
    run(['pwd'], context: $context->withPath('src/Attribute'));
    run(['pwd'], context: $context);
}
