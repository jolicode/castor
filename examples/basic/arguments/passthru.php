<?php

namespace arguments;

use Castor\Attribute\AsArgsAfterOptionEnd;
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

/**
 * @param string[] $argsAfterOptionEnd
 */
#[AsTask(description: 'Dumps all arguments and options, without configuration nor validation')]
function passthru_after_endoption(string $beforeArg, #[AsArgsAfterOptionEnd] array $argsAfterOptionEnd, bool $customOption = false): void
{
    var_dump($beforeArg);
    var_dump($customOption);
    var_dump($argsAfterOptionEnd);
}
