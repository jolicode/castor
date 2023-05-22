<?php

namespace cd;

use Castor\Attribute\Task;
use Castor\Context;

use function Castor\exec;

#[Task(description: 'A simple command that changes directory')]
function directory(Context $context)
{
    exec(['pwd'], context: $context);
    exec(['pwd'], context: $context->withCd('..'));
    exec(['pwd'], context: $context);
}
