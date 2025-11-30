<?php

namespace arguments;

use Castor\Attribute\AsArgsAfterOptionEnd;
use Castor\Attribute\AsTask;

use function Castor\run;

/**
 * @param string[] $argsAfterOptionEnd
 */
#[AsTask(description: 'Dumps all arguments and options after "--" without configuration nor validation')]
function args_after_end_option(string $before, #[AsArgsAfterOptionEnd] array $argsAfterOptionEnd): void
{
    if ($before === 'first') {
        run(['task1', ...$argsAfterOptionEnd]);
    }

    run(['task2', ...$argsAfterOptionEnd]);
}
