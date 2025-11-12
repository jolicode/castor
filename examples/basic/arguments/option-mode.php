<?php

namespace arguments;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Symfony\Component\Console\Input\InputOption;

use function Castor\io;

#[AsTask()]
function option_mode(
    #[AsOption(description: 'This is the foo option', mode: InputOption::VALUE_NONE)]
    bool $force,
): void {
    if ($force) {
        io()->writeln('Command has been forced.');
    }
}
