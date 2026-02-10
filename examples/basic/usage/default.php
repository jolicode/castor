<?php

namespace usage;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(description: 'Displays some help for the current project', default: true)]
function about(): void
{
    io()->title('About this project');

    io()->comment('Run <comment>castor list</comment> to display the command list.');
    io()->comment('Run <comment>castor usage:about</comment> to display this project help.');
    io()->comment('Run <comment>castor help [command]</comment> to display Castor help.');
}
