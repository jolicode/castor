<?php

namespace Castor\Console\Input;

use Symfony\Component\Console\Input\ArgvInput;

class Input extends ArgvInput
{
    /**
     * @return list<string>
     */
    public function getRawTokens(): array
    {
        // @phpstan-ignore-next-line
        return (fn () => $this->tokens)->bindTo($this, ArgvInput::class)();
    }
}
