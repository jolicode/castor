<?php

namespace ls;

use Castor\CommandBuilder\CommandBuilder;
use Castor\CommandBuilder\WithWorkingDirectoryTrait;

class Ls extends CommandBuilder
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
}

function ls(?string $directory = null): Ls
{
    return new Ls($directory);
}
