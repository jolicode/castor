<?php

namespace arguments;

use Castor\Attribute\AsPathArgument;
use Castor\Attribute\AsTask;

#[AsTask()]
function path_argument(#[AsPathArgument()] string $argument): void
{
}
