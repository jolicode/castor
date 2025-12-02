<?php

namespace arguments;

use Castor\Attribute\AsArgsAfterOptionEnd;
use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

/**
 * @param string[] $argsAfterOptionEnd
 */
#[AsTask(description: 'Dumps all arguments and options after the end of options marker (--).')]
function passthru_after_endoption(string $beforeArg, #[AsArgsAfterOptionEnd] array $argsAfterOptionEnd, bool $customOption = false): void
{
    var_dump($beforeArg);
    var_dump($customOption);
    var_dump($argsAfterOptionEnd);
}
