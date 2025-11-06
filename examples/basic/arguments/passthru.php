<?php

namespace arguments;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

/**
 * @param string[] $rawTokens
 */
#[AsTask(description: 'Dumps all arguments and options, without configuration nor validation')]
function passthru(#[AsRawTokens] array $rawTokens): void
{
    var_dump($rawTokens);
}
