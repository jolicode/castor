<?php

namespace slug;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\slug;

#[AsTask(description: 'Slugify strings')]
function slugify(): void
{
    io()->writeln(slug('Hello World! This is Castor.'));
    io()->writeln(slug('Hello World! This is Castor.', separator: '_'));
    io()->writeln(slug('좋은 아침이에요', locale: 'en'));
    io()->writeln(slug('좋은 아침이에요', locale: 'ko'));
}
