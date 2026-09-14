<?php

\defined('CASTOR_USE_CHDIR') || \define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(default: true)]
function about(): void
{
    io()->writeln('about');
}
