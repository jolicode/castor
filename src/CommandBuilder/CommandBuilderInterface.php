<?php

namespace Castor\CommandBuilder;

interface CommandBuilderInterface
{
    /**
     * @return string|array<string|\Stringable|int>
     */
    public function getCommand(): array|string;
}
