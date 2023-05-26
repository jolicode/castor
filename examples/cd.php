<?php

namespace cd;

use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\exec;

#[AsTask(description: 'Changes directory')]
function directory(Context $context)
{
    exec(['pwd'], context: $context);
    exec(['pwd'], context: $context->withPath('src/Attribute'));
    exec(['pwd'], context: $context);
}
