<?php

namespace ls;

use Castor\CommandBuilder\CommandBuilderInterface;
use Castor\CommandBuilder\ContextUpdaterInterface;
use Castor\CommandBuilder\WithWorkingDirectoryTrait;
use Castor\Context;

class Ls implements CommandBuilderInterface, ContextUpdaterInterface
{
    use WithWorkingDirectoryTrait;

    private string $flags = '';

    public function __construct(private ?string $directory = null)
    {
    }

    public function all(): static
    {
        if (str_contains($this->flags, 'a')) {
            return $this;
        }

        $new = clone $this;
        $new->flags .= 'a';

        return $new;
    }

    public function long(): static
    {
        if (str_contains($this->flags, 'l')) {
            return $this;
        }

        $new = clone $this;
        $new->flags .= 'l';

        return $new;
    }

    /** @return string[] */
    public function getCommand(): array
    {
        $command = ['ls'];

        if ($this->flags) {
            $command[] = '-' . $this->flags;
        }

        if ($this->directory) {
            $command[] = $this->directory;
        }

        return $command;
    }

    public function updateContext(Context $context): Context
    {
        if ($this->workingDirectory) {
            $context = $context->withWorkingDirectory($this->workingDirectory);
        }

        return $context;
    }
}

function ls(?string $directory = null): Ls
{
    return new Ls($directory);
}
