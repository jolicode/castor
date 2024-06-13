<?php

namespace Castor\CommandBuilder;

use Castor\Context;

abstract class CommandBuilder implements CommandBuilderInterface
{
    /** @var (callable(Context): Context)[] */
    private array $contextModifiers = [];

    public function getContextModifiers(): array
    {
        return $this->contextModifiers;
    }

    /**
     * @param (callable(Context): Context) $modifier
     */
    protected function addContextModifier(callable $modifier): void
    {
        $this->contextModifiers[] = $modifier;
    }
}
