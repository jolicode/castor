<?php

namespace arguments;

use Castor\Attribute\AsArgsAfterOptionEnd;
use Castor\Attribute\AsTask;

use function Castor\io;

/**
 * @param string[] $argsAfterOptionEnd
 */
#[AsTask(description: 'Dumps all arguments and options after "--" without configuration nor validation')]
function args_after_end_option(string $before, #[AsArgsAfterOptionEnd] array $argsAfterOptionEnd): void
{
    if ('first' === $before) {
        io()->writeln(\sprintf('We could run : `%s %s`.', $before, implode(' ', $argsAfterOptionEnd)));

        return;
    }

    io()->writeln(\sprintf('We might run : `%s %s`.', $before, implode(' ', $argsAfterOptionEnd)));
}
