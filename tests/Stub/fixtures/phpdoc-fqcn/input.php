<?php

namespace Test\CommandBuilder;

interface CommandBuilderInterface
{
    /**
     * @return string|array<string|\Stringable|\Symfony\Component\Console\Command\Command|int>
     */
    public function getCommand(): array|string;
}
