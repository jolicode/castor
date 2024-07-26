<?php

namespace output;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\task;

#[AsTask(description: 'Plays with Symfony Style')]
function output(): void
{
    io()->title('This is a title');

    io()->text(\sprintf('This is the task "%s"', task()->getName()));

    io()->comment('With IO, you can ask questions ...');
    $value = io()->ask('Tell me something');
    io()->writeln('You said: ' . $value);

    io()->comment('... show progress bars ...');
    io()->progressStart(100);
    for ($i = 0; $i < 100; ++$i) {
        io()->progressAdvance();
        usleep(20_000);
    }
    io()->progressFinish();

    io()->comment('... show table ...');
    io()->table(['Name', 'Age'], [
        ['Alice', 21],
        ['Bob', 42],
    ]);

    io()->success('This is a success message');
}
