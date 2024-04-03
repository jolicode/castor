<?php

use Castor\Attribute\AsTask;

use function Castor\import;
use function Castor\mount;

import(__DIR__ . '/examples');
import(__DIR__ . '/tools/php-cs-fixer/castor.php');
import(__DIR__ . '/tools/phpstan/castor.php');
import(__DIR__ . '/tools/static/castor.php');

mount(__DIR__ . '/tools/phar');
mount(__DIR__ . '/tools/watcher');

#[AsTask(description: 'hello')]
function hello(): void
{
    echo 'Hello world!';
}
