<?php

namespace assertion;

use Castor\Attribute\AsTask;
use Castor\Exception\ProblemException;

#[AsTask(description: 'Throws a Problem exception')]
function throw_an_exception(): never
{
    throw new ProblemException('Houston, we have a problem');
}
