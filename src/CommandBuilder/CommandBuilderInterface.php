<?php

namespace Castor\CommandBuilder;

use Castor\Context;

interface CommandBuilderInterface
{
    /**
     * @return string|array<string|\Stringable|int>
     */
    public function getCommand(): array|string;

    /**
     * @return (callable(Context): Context)[]
     */
    public function getContextModifiers(): array;
}
