<?php

namespace cd;

use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\exec;

#[AsTask(description: 'A simple command that changes directory')]
function directory(Context $context)
{
    exec(['pwd'], context: $context);
    exec(['pwd'], context: $context->withCd('..'));
    exec(['pwd'], context: $context);
}
