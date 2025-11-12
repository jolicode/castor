<?php

namespace arguments;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask()]
function phpunit(#[AsRawTokens] array $rawTokens): void
{
    run(['vendor/bin/simple-phpunit', ...$rawTokens]);
}
