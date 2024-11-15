<?php

namespace assertion;

use Castor\Attribute\AsTask;
use Castor\Exception\ProblemException;

use function Castor\check;

#[AsTask(description: 'Ensure we are in the future')]
function ensure_we_are_in_the_future(): void
{
    check(
        'Check if we are in the future',
        'We are not in the future 😱',
        fn () => (!usleep(500_000) && new \DateTime() > new \DateTime('2015-10-21'))
    );
}

#[AsTask(description: 'Throws a Problem exception')]
function throw_an_exception(): never
{
    throw new ProblemException('Houston, we have a problem');
}
