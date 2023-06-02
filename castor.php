<?php

use Castor\Attribute\AsTask;

use function Castor\import;

import(__DIR__ . '/examples');
import(__DIR__ . '/tools/phar/castor');
import(__DIR__ . '/tools/watcher/castor');

#[AsTask(description: 'hello')]
function hello(): void
{
    echo 'Hello world!';
}
